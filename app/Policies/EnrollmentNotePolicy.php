<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 *  受講生メモ の認可ポリシー。
 *
 * - admin: 全資格配下を CRUD 可
 * - coach: 担当資格(certification_coach_assignments)配下 かつ 自分が作成したメモ のみ 編集、削除 可
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $auth, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollment($auth, $enrollment);
    }

    public function view(User $auth, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;

        if ($enrollment === null){
            return false;
        }

        return $this->canAccessEnrollment($auth, $enrollment);
    }

    public function create(User $auth, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollment($auth, $enrollment);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $auth, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;

        if ($enrollment === null){
            return false;
        }

        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return $auth->role === UserRole::Coach
            && $note->author_user_id === $auth->id
            && $this->assignedCoach($auth, $enrollment);
    }

    public function delete(User $auth, EnrollmentNote $note): bool
    {
        return $this->update($auth, $note);
    }

    private function assignedCoach(User $coach, Enrollment $enrollment): bool
    {
        return $coach->assignedCertifications()
            ->where('certifications.id', $enrollment->certification_id)
            ->exists();
    }
    private function canAccessEnrollment(User $auth, Enrollment $enrollment): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $enrollment),

            default => false,
        };
    }
}
