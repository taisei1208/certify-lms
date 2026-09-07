<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_goal_for_own_learning_enrollment(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $response = $this
            ->actingAs($student)
            ->post(
                route('enrollments.goals.store', $enrollment),
                [
                    'title' => '過去問を5年分解く',
                    'description' => '毎日少しずつ進めます。',
                    'target_date' => today()
                        ->addDays(14)
                        ->toDateString(),
                ],
            );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHas(
                'success',
                '目標を追加しました。',
            );

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問を5年分解く',
            'description' => '毎日少しずつ進めます。',
            'target_date' => today()
                ->addDays(14)
                ->toDateString(),
            'achieved_at' => null,
        ]);
    }

    public function test_target_date_is_required(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $response = $this
            ->actingAs($student)
            ->from(route('enrollments.show', $enrollment))
            ->post(
                route('enrollments.goals.store', $enrollment),
                [
                    'title' => '目標',
                    'description' => null,
                    'target_date' => '',
                ],
            );

        $response
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHasErrors('target_date');

        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_past_target_date_is_rejected(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $response = $this
            ->actingAs($student)
            ->from(route('enrollments.show', $enrollment))
            ->post(
                route('enrollments.goals.store', $enrollment),
                [
                    'title' => '過去日の目標',
                    'description' => null,
                    'target_date' => today()
                        ->subDay()
                        ->toDateString(),
                ],
            );

        $response
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHasErrors('target_date');

        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_today_can_be_used_as_target_date(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $this
            ->actingAs($student)
            ->post(
                route('enrollments.goals.store', $enrollment),
                [
                    'title' => '今日の目標',
                    'description' => null,
                    'target_date' => today()->toDateString(),
                ],
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            );

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '今日の目標',
            'target_date' => today()->toDateString(),
        ]);
    }

    public function test_title_and_description_length_are_validated(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $response = $this
            ->actingAs($student)
            ->post(
                route('enrollments.goals.store', $enrollment),
                [
                    'title' => str_repeat('あ', 101),
                    'description' => str_repeat('い', 1001),
                    'target_date' => today()
                        ->addDay()
                        ->toDateString(),
                ],
            );

        $response->assertSessionHasErrors([
            'title',
            'description',
        ]);

        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_student_can_open_edit_page_for_own_goal(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '編集前の目標',
            ]);

        $this
            ->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertOk()
            ->assertViewIs('enrollment-goal.edit')
            ->assertViewHas('goal', $goal)
            ->assertSee('編集前の目標');
    }

    public function test_student_can_update_own_goal_without_changing_achievement(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $achievedAt = now()->subHour()->startOfSecond();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '編集前',
                'description' => '編集前の詳細',
                'target_date' => today()->addDays(10),
                'achieved_at' => $achievedAt,
            ]);

        $response = $this
            ->actingAs($student)
            ->patch(
                route('enrollment-goals.update', $goal),
                [
                    'title' => '編集後',
                    'description' => '編集後の詳細',
                    'target_date' => today()
                        ->addDays(20)
                        ->toDateString(),
                ],
            );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHas(
                'success',
                '目標を更新しました。',
            );

        $goal->refresh();

        $this->assertSame('編集後', $goal->title);
        $this->assertSame('編集後の詳細', $goal->description);
        $this->assertSame(
            today()->addDays(20)->toDateString(),
            $goal->target_date->toDateString(),
        );

        // 通常編集で達成状態が変更されていない
        $this->assertTrue(
            $goal->achieved_at->equalTo($achievedAt),
        );
    }

    public function test_student_can_delete_own_goal(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this
            ->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal))
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHas(
                'success',
                '目標を削除しました。',
            );

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_student_can_mark_goal_as_achieved(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'achieved_at' => null,
            ]);

        $this
            ->actingAs($student)
            ->post(
                route(
                    'enrollment-goals.markAchieved',
                    $goal,
                ),
            )
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHas(
                'success',
                '目標を達成済みにしました。',
            );

        $this->assertNotNull(
            $goal->fresh()->achieved_at,
        );
    }

    public function test_achieved_goal_cannot_be_marked_again(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'achieved_at' => now(),
            ]);

        $this
            ->actingAs($student)
            ->postJson(
                route(
                    'enrollment-goals.markAchieved',
                    $goal,
                ),
            )
            ->assertStatus(409);
    }

    public function test_student_can_return_goal_to_unachieved(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'achieved_at' => now(),
            ]);

        $this
            ->actingAs($student)
            ->delete(
                route(
                    'enrollment-goals.unmarkAchieved',
                    $goal,
                ),
            )
            ->assertRedirect(
                route('enrollments.show', $enrollment),
            )
            ->assertSessionHas(
                'success',
                '目標を未達成に戻しました。',
            );

        $this->assertNull(
            $goal->fresh()->achieved_at,
        );
    }

    public function test_unachieved_goal_cannot_be_unmarked_again(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'achieved_at' => null,
            ]);

        $this
            ->actingAs($student)
            ->deleteJson(
                route(
                    'enrollment-goals.unmarkAchieved',
                    $goal,
                ),
            )
            ->assertStatus(409);
    }

    public function test_goals_are_shown_unachieved_first_and_by_target_date(): void
    {
        [$student, $enrollment] = $this->createLearningEnrollment();

        EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '達成済みの目標',
                'target_date' => today()->addDay(),
                'achieved_at' => now(),
            ]);

        EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '未達成で期日が遅い目標',
                'target_date' => today()->addDays(20),
                'achieved_at' => null,
            ]);

        EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '未達成で期日が近い目標',
                'target_date' => today()->addDays(5),
                'achieved_at' => null,
            ]);

        $this
            ->actingAs($student)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSeeInOrder([
                '未達成で期日が近い目標',
                '未達成で期日が遅い目標',
                '達成済みの目標',
            ]);
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
