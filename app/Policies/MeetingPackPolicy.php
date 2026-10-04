<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * MeetingPackに対する認可ポリシー。
 *
 * 全 method admin のみ許可する。
 */
class MeetingPackPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin
            && ! $plan->payments()->exists();
    }

    public function delete(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin
            && ! $plan->payments()->exists();
    }

    public function publish(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
