<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の回答に対する認可ポリシー。
 *
 * - admin: 全 QaReply を 削除 のみ可
 * - coach: 担当資格(certification_coach_assignments) 配下のみ 投稿 可、投稿した本人のみ 編集 削除 可
 * - student: 全資格配下で `is_published = true` の QaThread に QaReply を投稿 可、作成した本人のみ編集と削除 可
 */
class QaReplyPolicy
{
    public function create(User $auth, QaThread $thread): bool
    {
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

    public function update(User $auth, QaReply $reply): bool
    {
        return $reply->user_id === $auth->id && $this->canAccessThread($auth, $reply->thread);
    }

    public function delete(User $auth, QaReply $reply): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return $reply->user_id === $auth->id && $this->canAccessThread($auth, $reply->thread);
    }

    private function canAccessThread(User $auth, QaThread $thread): bool
    {
        if (
            $thread->certification->status
            !== CertificationStatus::Published
        ) {
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
}
