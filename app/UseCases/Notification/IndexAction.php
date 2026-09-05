<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\user;

/**
 * ログインユーザー自身の通知一覧を取得するユースケース。
 */
final class IndexAction
{
    public function __invoke(user $user, string $tab): array
    {
        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        return [
            'notifications' => $query
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'unread_count' => $user
                ->unreadNotifications()
                ->count(),
        ];
    }
}
