<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板のスレッドに対する認可ポリシー。
 *
 * - admin: 全資格配下の QaThread を 閲覧、削除のみ可
 * - coach: 担当資格(certification_coach_assignments) 配下のみ 閲覧 可
 * - student: 全資格配下で `is_published = true` のQaThreadを閲覧、投稿 可、投稿した本人のみ編集と削除、解決マーク 可
 */
class QaThreadPolicy
{
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [
            UserRole::Student,
            UserRole::Coach,
            UserRole::Admin,
        ], true);
    }

    public function view(User $auth, QaThread $thread): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($thread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($auth->role) {
            UserRole::Student => true,
            UserRole::Coach => $thread->certification
                ->coaches()
                ->where('users.id', $auth->id)
                ->exists(),
            default => false,
        };
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    public function update(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $thread->user_id === $auth->id
            && $thread->certification->status === CertificationStatus::Published;
    }

    public function delete(User $auth, QaThread $thread): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return $auth->role === UserRole::Student
            && $thread->user_id === $auth->id
            && $thread->certification->status === CertificationStatus::Published;
    }

    public function resolve(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $thread->user_id === $auth->id
            && $thread->certification->status === CertificationStatus::Published;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function unresolve(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $thread->user_id === $auth->id
            && $thread->certification->status === CertificationStatus::Published;
    }
}
