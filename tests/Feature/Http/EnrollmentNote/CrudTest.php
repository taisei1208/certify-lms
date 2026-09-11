<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_create_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $this->actingAs($coach)
            ->post(
                route(
                    'enrollments.notes.store',
                    $enrollment,
                ),
                [
                    'body' => '次回面談で学習状況を確認します。',
                ],
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertSessionHas(
                'success',
                'メモを追加しました。',
            );

        $this->assertDatabaseHas(
            'enrollment_notes',
            [
                'enrollment_id' => $enrollment->id,
                'author_user_id' => $coach->id,
                'body' => '次回面談で学習状況を確認します。',
            ],
        );
    }

    public function test_admin_can_create_note(): void
    {
        [$enrollment] = $this->createEnrollment();

        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs($admin)
            ->post(
                route(
                    'enrollments.notes.store',
                    $enrollment,
                ),
                [
                    'body' => '管理者が作成したメモです。',
                ],
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            );

        $this->assertDatabaseHas(
            'enrollment_notes',
            [
                'enrollment_id' => $enrollment->id,
                'author_user_id' => $admin->id,
                'body' => '管理者が作成したメモです。',
            ],
        );
    }

    public function test_note_body_is_required(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $this->actingAs($coach)
            ->from(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->post(
                route(
                    'enrollments.notes.store',
                    $enrollment,
                ),
                [
                    'body' => '',
                ],
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount(
            'enrollment_notes',
            0,
        );
    }

    public function test_note_body_cannot_exceed_2000_characters(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $this->actingAs($coach)
            ->from(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->post(
                route(
                    'enrollments.notes.store',
                    $enrollment,
                ),
                [
                    'body' => str_repeat('あ', 2001),
                ],
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertSessionHasErrors('body');
    }

    public function test_assigned_coach_can_view_notes_written_by_other_coach(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $otherCoach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $this->assignCoach(
            $otherCoach,
            $certification,
        );

        EnrollmentNote::factory()
            ->for($enrollment)
            ->for($otherCoach, 'author')
            ->create([
                'body' => '他コーチが作成したメモです。',
            ]);

        $this->actingAs($coach)
            ->get(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertOk()
            ->assertSee(
                '他コーチが作成したメモです。',
            );
    }

    public function test_coach_can_update_own_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => '変更前のメモです。',
            ]);

        $this->actingAs($coach)
            ->patch(
                route(
                    'enrollment-notes.update',
                    $note,
                ),
                [
                    'body' => '変更後のメモです。',
                ],
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertSessionHas(
                'success',
                'メモを更新しました。',
            );

        $this->assertDatabaseHas(
            'enrollment_notes',
            [
                'id' => $note->id,
                'body' => '変更後のメモです。',
            ],
        );
    }

    public function test_coach_can_physically_delete_own_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $this->actingAs($coach)
            ->delete(
                route(
                    'enrollment-notes.destroy',
                    $note,
                ),
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertSessionHas(
                'success',
                'メモを削除しました。',
            );

        $this->assertDatabaseMissing(
            'enrollment_notes',
            [
                'id' => $note->id,
            ],
        );
    }

    public function test_coach_cannot_update_other_coach_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $otherCoach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $this->assignCoach(
            $otherCoach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($otherCoach, 'author')
            ->create([
                'body' => '他コーチのメモです。',
            ]);

        $this->actingAs($coach)
            ->patch(
                route(
                    'enrollment-notes.update',
                    $note,
                ),
                [
                    'body' => '不正な変更です。',
                ],
            )
            ->assertForbidden();

        $this->assertSame(
            '他コーチのメモです。',
            $note->fresh()->body,
        );
    }

    public function test_coach_cannot_delete_other_coach_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $otherCoach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $this->assignCoach(
            $otherCoach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($otherCoach, 'author')
            ->create();

        $this->actingAs($coach)
            ->delete(
                route(
                    'enrollment-notes.destroy',
                    $note,
                ),
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'enrollment_notes',
            [
                'id' => $note->id,
            ],
        );
    }

    public function test_admin_can_update_any_note(): void
    {
        [$enrollment] = $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $this->actingAs($admin)
            ->patch(
                route(
                    'enrollment-notes.update',
                    $note,
                ),
                [
                    'body' => '管理者が修正したメモです。',
                ],
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            );

        $this->assertDatabaseHas(
            'enrollment_notes',
            [
                'id' => $note->id,
                'body' => '管理者が修正したメモです。',
            ],
        );
    }

    public function test_admin_can_delete_any_note(): void
    {
        [$enrollment] = $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $this->actingAs($admin)
            ->delete(
                route(
                    'enrollment-notes.destroy',
                    $note,
                ),
            )
            ->assertRedirect(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            );

        $this->assertDatabaseMissing(
            'enrollment_notes',
            [
                'id' => $note->id,
            ],
        );
    }

    public function test_unassigned_coach_cannot_create_note(): void
    {
        [$enrollment] = $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->actingAs($coach)
            ->post(
                route(
                    'enrollments.notes.store',
                    $enrollment,
                ),
                [
                    'body' => '担当外からのメモです。',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'enrollment_notes',
            0,
        );
    }

    public function test_unassigned_coach_cannot_update_own_previous_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $assignment = $this->assignCoach(
            $coach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => '担当中に作成したメモです。',
            ]);

        $assignment->update([
            'unassigned_at' => now(),
        ]);

        $this->actingAs($coach)
            ->patch(
                route(
                    'enrollment-notes.update',
                    $note,
                ),
                [
                    'body' => '担当解除後の変更です。',
                ],
            )
            ->assertForbidden();

        $this->assertSame(
            '担当中に作成したメモです。',
            $note->fresh()->body,
        );
    }

    public function test_unassigned_coach_cannot_delete_own_previous_note(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $assignment = $this->assignCoach(
            $coach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $assignment->update([
            'unassigned_at' => now(),
        ]);

        $this->actingAs($coach)
            ->delete(
                route(
                    'enrollment-notes.destroy',
                    $note,
                ),
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'enrollment_notes',
            [
                'id' => $note->id,
            ],
        );
    }

    public function test_student_cannot_create_note(): void
    {
        [$enrollment] = $this->createEnrollment();

        $student = $enrollment->user;

        $this->actingAs($student)
            ->post(
                route(
                    'enrollments.notes.store',
                    $enrollment,
                ),
                [
                    'body' => '受講生が作成したメモです。',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'enrollment_notes',
            0,
        );
    }

    public function test_student_cannot_see_note_section(): void
    {
        [$enrollment] = $this->createEnrollment();

        $student = $enrollment->user;

        $coach = User::factory()
            ->coach()
            ->create();

        EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => '受講生には見えてはいけないメモです。',
            ]);

        $this->actingAs($student)
            ->get(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertOk()
            ->assertDontSee('コーチメモ')
            ->assertDontSee(
                '受講生には見えてはいけないメモです。',
            );
    }

    public function test_notes_are_displayed_newest_first(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => '古いメモです。',
                'created_at' => now()->subDays(2),
            ]);

        EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create([
                'body' => '新しいメモです。',
                'created_at' => now(),
            ]);

        $this->actingAs($coach)
            ->get(
                route(
                    'enrollments.show',
                    $enrollment,
                ),
            )
            ->assertOk()
            ->assertSeeInOrder([
                '新しいメモです。',
                '古いメモです。',
            ]);
    }

    public function test_note_cannot_be_accessed_after_parent_enrollment_is_deleted(): void
    {
        [$enrollment, $certification] =
            $this->createEnrollment();

        $coach = User::factory()
            ->coach()
            ->create();

        $this->assignCoach(
            $coach,
            $certification,
        );

        $note = EnrollmentNote::factory()
            ->for($enrollment)
            ->for($coach, 'author')
            ->create();

        $enrollment->delete();

        $this->actingAs($coach)
            ->get(
                route(
                    'enrollment-notes.edit',
                    $note,
                ),
            )
            ->assertForbidden();
    }

    /**
     * @return array{0: Enrollment, 1: Certification}
     */
    private function createEnrollment(): array
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
            $enrollment,
            $certification,
        ];
    }

    private function assignCoach(
        User $coach,
        Certification $certification,
    ): CertificationCoachAssignment {
        $admin = User::factory()
            ->admin()
            ->create();

        return CertificationCoachAssignment::factory()
            ->create([
                'user_id' => $coach->id,
                'certification_id' =>
                    $certification->id,
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'unassigned_at' => null,
            ]);
    }
}