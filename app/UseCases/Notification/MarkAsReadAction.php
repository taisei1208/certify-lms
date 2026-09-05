<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 通知一件を個別に既読化するユースケース。
 *
 * 本人の通知から検索することで、他人の通知IDを指定した場合404になる。
 */
final class MarkAsReadAction
{
    public function __invoke(User $user, string $notificationId): string
    {
        $notification = $user->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return $notification->data['url']
            ?? route('notifications.index');
    }
}
