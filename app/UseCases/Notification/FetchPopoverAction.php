<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 通知ポップオーバーへ表示する通知を取得する。
 */
final class FetchPopoverAction
{
    private const LIMIT = 20;

    public function __invoke(User $user, string $tab): array
    {
        $notifications = $user->notifications()
            ->when($tab === 'unread',
                fn ($query) => $query->whereNull('read_at'),
            )
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        return [
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }
}
