<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use App\Services\NotificationRecipientService;

/**
 * 質問スレッドへ回答を投稿する。
 */
final class StoreAction
{
    public function __construct(private readonly NotificationRecipientService $recipients) {}

    public function __invoke(User $author, QaThread $thread, string $body): QaReply
    {
        $reply = $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        $thread->loadMissing('user');

        if ($thread->user_id !== $author->id && $this->recipients->canReceive($thread->user)) {
            try {
                $thread->user->notify(new QaReplyReceivedNotification($reply));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $reply;
    }
}
