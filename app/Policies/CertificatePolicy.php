<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証PDFをダウンロードできるか判定する。
 */
class CertificatePolicy
{
    /**
     * Create a new policy instance.
     */
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $certificate->user_id === $user->id,
            UserRole::Coach => $certificate->certification
                ->coaches()
                ->whereKey($user->id)
                ->exists(),

            default => false,
        };
    }
}
