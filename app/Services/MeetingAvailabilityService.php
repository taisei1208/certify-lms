<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 担当コーチ集合の面談可能時間枠を 60 分単位で展開し、空きスロットを Google Calendarの予定を除外して、予約可能なスロットを集計するService。。
 *
 * 受講生の予約画面が「該当資格の担当コーチ全員の有効枠 Union」を 1 日単位で取得し、
 * 既存予約済時刻 を除外して各スロットの「予約可能なコーチ数」を返す。受講生にコーチ個別は提示せず、
 * 予約確定時にコーチを自動割当する。
 */
final class MeetingAvailabilityService
{
    /**
     * 同一リクエスト内で同じコーチ・同じ日の
     * Google APIを繰り返し呼ばないためのキャッシュ。
     *
     * @var array<string, Collection>
     */
    private array $googleBusyPeriodsCache = [];

    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * 指定 Certification の担当コーチ集合について、指定日 1 日分の 60 分単位空きスロットを返す。
     *
     * 1 リクエストあたり availability 1 クエリ + meetings 1 クエリ で完結させる。
     *
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function slotsForCertification(Certification $certification, Carbon $date): Collection
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $dayOfWeek = $date->dayOfWeek;

        $coaches = $certification->coaches()->with('googleCredential')->get();
        if ($coaches->isEmpty()) {
            return collect();
        }

        $coachesById = $coaches->keyBy('id');
        $coachIds = $coaches->pluck('id')->all();

        $availabilities = CoachAvailability::query()
            ->whereIn('coach_id', $coachIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $existingMeetings = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value])
            ->get(['coach_id', 'scheduled_at']);

        // 予約済スロットを (coach_id => Set<H:i>) で索引化
        $bookedByCoach = $existingMeetings
            ->groupBy('coach_id')
            ->map(fn ($rows) => $rows->map(fn (Meeting $m) => $m->scheduled_at->format('H:i'))->all());

        /** @var array<string, int> $slotCounts スロット開始時刻(H:i) → available coach 数 */
        $slotCounts = [];

        foreach ($availabilities as $availability) {
            $slot = Carbon::parse($date->format('Y-m-d').' '.$availability->start_time);
            $end = Carbon::parse($date->format('Y-m-d').' '.$availability->end_time);

            while ($slot->copy()->addHour() <= $end) {
                $slotKey = $slot->format('H:i');
                $coachId = $availability->coach_id;
                $booked = $bookedByCoach[$coachId] ?? [];

                $coach = $coachesById->get($coachId);

                $availableOnGoogle = $coach instanceof User && $this->isCoachAvailableOnGoogle($coach, $slot);

                if (! in_array($slotKey, $booked, true) && $availableOnGoogle) {
                    $slotCounts[$slotKey] = ($slotCounts[$slotKey] ?? 0) + 1;
                }

                $slot->addHour();
            }
        }

        ksort($slotCounts);

        return collect($slotCounts)->map(function (int $count, string $time) use ($date) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$time);

            return [
                'slot_start' => $start,
                'slot_end' => $start->copy()->addHour(),
                'available_coach_count' => $count,
            ];
        })->values();
    }

    /**
     * 指定 scheduled_at が certification 担当コーチ集合の有効枠内かを検証する。
     * 枠外なら MeetingOutOfAvailabilityException を throw する。
     *
     * @throws MeetingOutOfAvailabilityException
     */
    public function validateSlot(Certification $certification, Carbon $scheduledAt): void
    {
        $slots = $this->slotsForCertification($certification, $scheduledAt->copy()->startOfDay());

        $matched = $slots->contains(
            fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt) && $slot['available_coach_count'] > 0,
        );

        if (! $matched) {
            throw new MeetingOutOfAvailabilityException;
        }
    }

    /**
     * 指定した60分枠について、コーチがGoogle Calendar上で空いているかを判定する。
     */
    public function isCoachAvailableOnGoogle(User $coach, Carbon $scheduledAt): bool
    {
        $busyPeriods = $this->googleBusyPeriodsForDay($coach, $scheduledAt);

        $slotStart = $scheduledAt->copy();
        $slotEnd = $slotStart->copy()->addHour();

        return ! $busyPeriods->contains(
            function (array $busyPeriod) use ($slotStart, $slotEnd): bool {
                $busyStart = $busyPeriod['start'];
                $busyEnd = $busyPeriod['end'];

                return $busyStart->lt($slotEnd) && $busyEnd->gt($slotStart);
            }
        );
    }

    /**
     * コーチの指定日1日分のGoogle予定を取得する。
     *
     * 同じリクエスト内ではコーチ・日付単位で結果をキャッシュする。
     */
    private function googleBusyPeriodsForDay(User $coach, Carbon $date): Collection
    {
        $cacheKey = $coach->id.'|'.$date->format('Y-m-d');

        if (
            array_key_exists($cacheKey, $this->googleBusyPeriodsCache)
        ) {
            return $this->googleBusyPeriodsCache[$cacheKey];
        }

        $coach->loadMissing('googleCredential');

        if ($coach->googleCredential === null) {
            return $this->googleBusyPeriodsCache[$cacheKey] = collect();
        }

        try {
            return $this->googleBusyPeriodsCache[$cacheKey] = $this->googleCalendarService->busyPeriods(
                $coach,
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            );
        } catch (Throwable $exception) {
            Log::warning('Google Calendarの予定取得に失敗しました。',
                [
                    'coach_id' => $coach->id,
                    'date' => $date->toDateString(),
                    'exception' => $exception->getMessage(),
                ],
            );

            /*
             * Google通信失敗時は予定なしとして扱う。
             */
            return $this->googleBusyPeriodsCache[$cacheKey] = collect();
        }
    }
}
