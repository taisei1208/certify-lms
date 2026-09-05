<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 通知を全件既読化するユースケース。
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
