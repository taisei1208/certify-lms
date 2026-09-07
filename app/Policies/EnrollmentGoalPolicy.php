<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人学習目標の認可ポリシー。
 *
 * - student: 受講中の自分のEnrollment配下のみ操作可能
 * - coach: 担当資格に属するEnrollmentの目標を閲覧のみ可能
 * - admin: 全Enrollmentの目標を閲覧のみ可能
 */
class EnrollmentGoalPolicy
{
    public function viewAny(User $auth, Enrollment $enrollment): bool
    {
        return $auth->can('view', $enrollment);
    }

    public function view(User $auth, EnrollmentGoal $goal): bool
    {
        return $auth->can('view', $goal->enrollment);
    }

    public function create(User $auth, Enrollment $enrollment): bool
    {
        return $this->canManage($auth, $enrollment);
    }

    public function update(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->canManage($auth, $goal->enrollment);
    }

    public function delete(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->canManage($auth, $goal->enrollment);
    }

    public function markAchieved(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->canManage($auth, $goal->enrollment);
    }

    public function unmarkAchieved(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->canManage($auth, $goal->enrollment);
    }

    /**
     * 受講生本人が、受講中のEnrollment配下を操作しているか。
     */
    private function canManage(User $auth, Enrollment $enrollment): bool
    {
        return $auth->role === UserRole::Student
            && $auth->status === UserStatus::InProgress
            && $enrollment->user_id === $auth->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }
}
