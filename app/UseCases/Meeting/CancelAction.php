<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\Services\NotificationRecipientService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly NotificationRecipientService $recipients,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor): void {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);

            $recipient = $actor->id === $locked->student_id
                ? $locked->coach
                : $locked->student;

            if ($this->recipients->canReceive($recipient)) {
                DB::afterCommit(function () use ($locked, $recipient): void {
                    try {
                        $recipient->notify(new MeetingCanceledNotification($locked)
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            }

            DB::afterCommit(function () use ($meeting): void {
                $this->deleteGoogleCalendarEvent($meeting);
            });
        });
    }

    /**
     * Googleカレンダーから面談イベントを削除する。
     *
     * 削除に失敗した場合はイベントIDを残し、
     * LMS上のキャンセル処理には影響させない。
     */
    private function deleteGoogleCalendarEvent(Meeting $meeting): void
    {
        /*
         * afterCommit時点の最新データを取得する。
         */
        $meeting = Meeting::query()->with('coach.googleCredential')->find($meeting->getKey());

        if ($meeting === null) {
            return;
        }

        $eventId = $meeting->google_calendar_event_id;

        if ($eventId === null) {
            return;
        }

        $coach = $meeting->coach;

        /*
         * Google連携が解除されている場合は削除できないため、
         * イベントIDを残して終了する。
         */
        if ($coach === null || $coach->googleCredential === null) {
            return;
        }

        try {
            $this->googleCalendarService->deleteMeetingEvent(
                $coach, $eventId,
            );

            /*
             * 削除に成功した場合だけイベントIDを消す。
             */
            Meeting::query()
                ->whereKey($meeting->getKey())
                ->where('google_calendar_event_id', $eventId)
                ->update([
                    'google_calendar_event_id' => null,
                ]);
        } catch (Throwable $exception) {
            Log::warning(
                '面談キャンセル時のGoogleカレンダーイベント削除に失敗しました。',
                [
                    'meeting_id' => $meeting->id,
                    'coach_id' => $coach->id,
                    'google_calendar_event_id' => $eventId,
                    'exception' => $exception->getMessage(),
                ],
            );

            report($exception);
        }
    }
}
