<?php

declare(strict_types=1);

namespace App\UseCases\MeetingReminder;

use App\Enums\MeetingReminderType;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\MeetingReminderDelivery;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * 対象時間帯の予約済み面談について、
 * 受講生とコーチへリマインダーを配信する。
 */
final class SendMeetingRemindersAction
{
    private const CHUNK_SIZE = 100;

    /**
     * @return int 配信に成功した通知件数
     */
    public function __invoke(
        MeetingReminderType $reminderType,
        ?CarbonImmutable $now = null,
    ): int {
        $now ??= CarbonImmutable::now();

        [$from, $to] = $this->targetPeriod(
            $reminderType,
            $now,
        );

        $deliveredCount = 0;

        Meeting::query()
            ->with([
                'student',
                'coach',
            ])
            ->where(
                'status',
                MeetingStatus::Reserved->value,
            )
            ->whereBetween(
                'scheduled_at',
                [$from, $to],
            )
            ->chunkById(
                self::CHUNK_SIZE,
                function (
                    Collection $meetings,
                ) use (
                    $reminderType,
                    &$deliveredCount,
                ): void {
                    foreach ($meetings as $meeting) {
                        foreach (
                            $this->recipients($meeting) as $recipient
                        ) {
                            $sent = $this->notify(
                                $meeting,
                                $recipient,
                                $reminderType,
                            );

                            if ($sent) {
                                $deliveredCount++;
                            }
                        }
                    }
                },
            );

        return $deliveredCount;
    }

    /**
     * リマインダー種別に対応する面談開始日時の範囲を返す。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function targetPeriod(
        MeetingReminderType $reminderType,
        CarbonImmutable $now,
    ): array {
        return match ($reminderType) {
            // 毎日18:00に実行し、翌日中の面談を対象にする
            MeetingReminderType::Eve => [
                $now->addDay()->startOfDay(),
                $now->addDay()->endOfDay(),
            ],

            // 毎分実行し、ちょうど1時間後の1分間を対象にする
            MeetingReminderType::OneHourBefore => [
                $now->addHour()->startOfMinute(),
                $now->addHour()->endOfMinute(),
            ],
        };
    }

    /**
     * 面談の当事者のうち、通知可能なユーザーを取得する。
     *
     * @return Collection<int, User>
     */
    private function recipients(
        Meeting $meeting,
    ): Collection {
        return new Collection([
            $meeting->student,
            $meeting->coach,
        ])
            ->filter(
                fn (?User $user): bool => $user !== null
                    && $user->status === UserStatus::InProgress
                    && in_array(
                        $user->role,
                        [
                            UserRole::Student,
                            UserRole::Coach,
                        ],
                        true,
                    ),
            )
            ->unique('id')
            ->values();
    }

    /**
     * 未配信の場合だけ通知し、配信実績を保存する。
     */
    private function notify(
        Meeting $meeting,
        User $recipient,
        MeetingReminderType $reminderType,
    ): bool {
        $delivery = MeetingReminderDelivery::query()
            ->createOrFirst(
                [
                    'meeting_id' => $meeting->id,
                    'user_id' => $recipient->id,
                    'reminder_type' => $reminderType->value,
                ],
                [
                    'delivered_at' => null,
                ],
            );

        // 同じ面談・受信者・種別がすでに存在する場合は送らない
        if (! $delivery->wasRecentlyCreated) {
            return false;
        }

        try {
            $recipient->notify(
                new MeetingReminderNotification(
                    $meeting,
                    $reminderType,
                ),
            );

            $delivery->update([
                'delivered_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            // 送信失敗時は次回実行で再試行できるようにする
            $delivery->delete();

            throw $exception;
        }
    }
}
