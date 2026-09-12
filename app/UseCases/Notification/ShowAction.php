<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * ログインユーザー本人宛の通知詳細を取得する。
 */
final class ShowAction
{
    public function __invoke(User $user, string $notificationId
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return $notification;
    }
}
