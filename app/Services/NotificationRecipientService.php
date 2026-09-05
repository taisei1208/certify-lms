<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * 配信対象の共通判定。
 *
 * 生徒もしくはコーチかつ受講中のユーザーにだけ配信する。
 */
final class NotificationRecipientService
{
    public function canReceive(User $user): bool
    {
        return in_array(
            $user->role,
            [
                UserRole::Student,
                UserRole::Coach,
            ],
            true,
        ) && $user->status === UserStatus::InProgress;
    }
}
