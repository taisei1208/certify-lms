<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\Services\NotificationRecipientService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly NotificationRecipientService $recipients,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(
        Enrollment $enrollment,
        Carbon $scheduledAt,
        ?string $topic,
    ): Meeting {
        $student = $enrollment->user;

        return DB::transaction(function () use (
            $enrollment,
            $student,
            $scheduledAt,
            $topic,
        ) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot(
                $enrollment->certification,
                $scheduledAt,
            );

            $candidates = $this->findAvailableCoaches(
                $enrollment->certification,
                $scheduledAt,
            )->filter(
                fn (User $coach): bool => $this->availabilityService->isCoachAvailableOnGoogle($coach, $scheduledAt))
                ->values();

            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)(
                $student,
                $meeting->id,
            );

            $meeting->update([
                'meeting_quota_transaction_id' => $transaction->id,
            ]);

            if ($this->recipients->canReceive($coach)) {
                DB::afterCommit(function () use ($meeting): void {
                    try {
                        $meeting->coach->notify(
                            new MeetingReservedNotification($meeting),
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            }

            DB::afterCommit(function () use ($meeting): void {
                $this->registerGoogleCalendarEvent($meeting);
            });

            return $meeting->fresh();
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(
        Certification $certification,
        Carbon $scheduledAt,
    ): Collection {
        $time = $scheduledAt->format('H:i:s');

        return $certification->coaches()
            ->whereHas(
                'coachAvailabilities',
                function ($q) use ($scheduledAt, $time) {
                    $q->where('day_of_week', $scheduledAt->dayOfWeek)
                        ->where('is_active', true)
                        ->where('start_time', '<=', $time)
                        ->where('end_time', '>', $time);
                },
            )
            ->whereDoesntHave(
                'meetingsAsCoach',
                function ($q) use ($scheduledAt) {
                    $q->where('scheduled_at', $scheduledAt)
                        ->whereIn('status', [
                            MeetingStatus::Reserved->value,
                            MeetingStatus::Completed->value,
                        ]);
                },
            )
            ->with('googleCredential')
            ->get();
    }

    /**
     * 連携済みコーチのGoogleカレンダーへ面談予定を登録する。
     *
     * Google側で失敗しても、LMS上の面談予約は取り消さない。
     */
    private function registerGoogleCalendarEvent(Meeting $meeting): void
    {
        $meeting->loadMissing([
            'coach.googleCredential',
            'student',
            'enrollment.certification',
        ]);

        $coach = $meeting->coach;

        if ($coach === null || $coach->googleCredential === null) {
            return;
        }

        try {
            $eventId = $this->googleCalendarService
                ->createMeetingEvent($coach, $meeting);

            Meeting::query()
                ->whereKey($meeting->getKey())
                ->update(['google_calendar_event_id' => $eventId,
                ]);
        } catch (Throwable $exception) {
            Log::warning(
                '面談予約のGoogleカレンダーイベント登録に失敗しました。',
                [
                    'meeting_id' => $meeting->id,
                    'coach_id' => $coach->id,
                    'exception' => $exception->getMessage(),
                ],
            );
        }
    }
}
