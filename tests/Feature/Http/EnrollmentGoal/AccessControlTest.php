<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_student_cannot_view_enrollment_goals(): void
    {
        [$owner, $enrollment] = $this->createLearningEnrollment();

        EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '本人だけの目標',
            ]);

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $this
            ->actingAs($otherStudent)
            ->get(route('enrollments.show', $enrollment))
            ->assertForbidden();
    }

    public function test_other_student_cannot_edit_goal_by_direct_url(): void
    {
        [$owner, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $this
            ->actingAs($otherStudent)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();
    }

    public function test_other_student_cannot_update_delete_or_achieve_goal(): void
    {
        [$owner, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'achieved_at' => null,
            ]);

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $this
            ->actingAs($otherStudent)
            ->patch(
                route('enrollment-goals.update', $goal),
                [
                    'title' => '不正更新',
                    'description' => null,
                    'target_date' => today()
                        ->addDay()
                        ->toDateString(),
                ],
            )
            ->assertForbidden();

        $this
            ->actingAs($otherStudent)
            ->delete(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();

        $this
            ->actingAs($otherStudent)
            ->post(
                route(
                    'enrollment-goals.markAchieved',
                    $goal,
                ),
            )
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => $goal->title,
            'achieved_at' => null,
        ]);
    }

    public function test_student_cannot_create_goal_for_another_student_enrollment(): void
    {
        [$owner, $enrollment] = $this->createLearningEnrollment();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $this
            ->actingAs($otherStudent)
            ->post(
                route('enrollments.goals.store', $enrollment),
                [
                    'title' => '不正な目標',
                    'description' => null,
                    'target_date' => today()
                        ->addDay()
                        ->toDateString(),
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '不正な目標',
        ]);
    }

    public function test_assigned_coach_can_view_goals_but_cannot_operate_them(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => 'コーチから見える目標',
            ]);

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        CertificationCoachAssignment::query()->create([
            'id' => (string) Str::ulid(),
            'certification_id' => $enrollment->certification_id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $this
            ->actingAs($coach)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('コーチから見える目標')
            ->assertDontSee(
                route('enrollment-goals.edit', $goal),
                false,
            )
            ->assertDontSee(
                route('enrollment-goals.destroy', $goal),
                false,
            );

        // 受講生専用ルートなので直接アクセスも拒否される
        $this
            ->actingAs($coach)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();
    }

    public function test_unassigned_coach_cannot_view_goals(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $coach = User::factory()->coach()->create();

        $this
            ->actingAs($coach)
            ->get(route('enrollments.show', $enrollment))
            ->assertForbidden();
    }

    public function test_admin_can_view_goals_but_cannot_operate_them(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '管理者から見える目標',
            ]);

        $admin = User::factory()->admin()->create();

        $this
            ->actingAs($admin)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('管理者から見える目標')
            ->assertDontSee(
                route('enrollment-goals.edit', $goal),
                false,
            )
            ->assertDontSee(
                route('enrollment-goals.destroy', $goal),
                false,
            );

        $this
            ->actingAs($admin)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();
    }

    public function test_graduated_student_cannot_operate_goal(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $student->update([
            'status' => UserStatus::Graduated,
        ]);

        $this
            ->actingAs($student->fresh())
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();

        $this
            ->actingAs($student->fresh())
            ->post(
                route(
                    'enrollment-goals.markAchieved',
                    $goal,
                ),
            )
            ->assertForbidden();
    }

    public function test_non_learning_enrollment_goal_cannot_be_operated(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this
            ->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();

        $this
            ->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Enrollment}
     */
    private function createLearningEnrollment(): array
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        return [
            $student,
            $enrollment,
        ];
    }
}
