<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\NotificationRecipientService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly NotificationRecipientService $recipients,
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
        });
    }
}
