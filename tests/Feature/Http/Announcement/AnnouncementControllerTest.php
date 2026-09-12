<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_history(): void
    {
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::factory()->create([
            'title' => '履歴表示確認のお知らせ',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertViewIs('announcement.management.index')
            ->assertSee($announcement->title);
    }

    public function test_admin_can_view_announcement_create_form(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->actingAs($admin)
            ->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertViewIs('announcement.management.create')
            ->assertSee($student->name)
            ->assertSee($certification->name);
    }

    public function test_admin_can_send_announcement_to_all_active_students(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $firstStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $secondStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.announcements.store'),
                [
                    'title' => '全受講生へのお知らせ',
                    'body' => '全受講生向けのお知らせ本文です。',
                    'target_type' => AnnouncementTargetType::AllStudents->value,
                ],
            );

        $announcement = Announcement::query()
            ->where('title', '全受講生へのお知らせ')
            ->firstOrFail();

        $response->assertRedirect(
            route('admin.announcements.show', $announcement),
        );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 2,
        ]);

        $this->assertNotNull($announcement->dispatched_at);

        Notification::assertSentTo(
            [$firstStudent, $secondStudent],
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            [$graduatedStudent, $coach, $admin],
            AdminAnnouncementNotification::class,
        );
    }

    public function test_certification_announcement_is_sent_only_to_active_learning_students(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $learningStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $failedStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        Enrollment::factory()
            ->learning()
            ->for($learningStudent, 'user')
            ->for($certification)
            ->create();

        Enrollment::factory()
            ->failed()
            ->for($failedStudent, 'user')
            ->for($certification)
            ->create();

        Enrollment::factory()
            ->learning()
            ->for($graduatedStudent, 'user')
            ->for($certification)
            ->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.announcements.store'),
                [
                    'title' => '資格指定のお知らせ',
                    'body' => '指定資格の受講生向け本文です。',
                    'target_type' => AnnouncementTargetType::Certification->value,
                    'target_certification_id' => $certification->id,
                ],
            );

        $announcement = Announcement::query()
            ->where('title', '資格指定のお知らせ')
            ->firstOrFail();

        $response->assertRedirect(
            route('admin.announcements.show', $announcement),
        );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
            'dispatched_count' => 1,
        ]);

        Notification::assertSentTo(
            $learningStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            [
                $failedStudent,
                $otherStudent,
                $graduatedStudent,
            ],
            AdminAnnouncementNotification::class,
        );
    }

    public function test_user_announcement_is_sent_only_to_selected_student(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $targetStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.announcements.store'),
                [
                    'title' => '個別のお知らせ',
                    'body' => '指定した受講生だけに送る本文です。',
                    'target_type' => AnnouncementTargetType::User->value,
                    'target_user_id' => $targetStudent->id,
                ],
            );

        $announcement = Announcement::query()
            ->where('title', '個別のお知らせ')
            ->firstOrFail();

        $response->assertRedirect(
            route('admin.announcements.show', $announcement),
        );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $targetStudent->id,
            'dispatched_count' => 1,
        ]);

        Notification::assertSentTo(
            $targetStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_certification_is_required_for_certification_target(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(
                route('admin.announcements.store'),
                [
                    'title' => '資格未指定',
                    'body' => 'お知らせ本文です。',
                    'target_type' => AnnouncementTargetType::Certification->value,
                ],
            )
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_certification_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_user_is_required_for_user_target(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(
                route('admin.announcements.store'),
                [
                    'title' => 'ユーザー未指定',
                    'body' => 'お知らせ本文です。',
                    'target_type' => AnnouncementTargetType::User->value,
                ],
            )
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_user_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_graduated_student_cannot_be_selected_as_user_target(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $this->actingAs($admin)
            ->post(
                route('admin.announcements.store'),
                [
                    'title' => '不正な対象ユーザー',
                    'body' => 'お知らせ本文です。',
                    'target_type' => AnnouncementTargetType::User->value,
                    'target_user_id' => $graduatedStudent->id,
                ],
            )
            ->assertSessionHasErrors('target_user_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_student_and_coach_cannot_access_announcement_management(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $announcement = Announcement::factory()->create();

        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)
                ->get(route('admin.announcements.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.announcements.create'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(
                    route(
                        'admin.announcements.show',
                        $announcement,
                    ),
                )
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.announcements.index'))
            ->assertRedirect('/login');
    }
}
