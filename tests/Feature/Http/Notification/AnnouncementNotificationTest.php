<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_can_view_announcement_notification_detail(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $announcement = Announcement::factory()->create([
            'title' => '通知詳細のタイトル',
            'body' => '通知詳細に表示する本文です。',
        ]);

        $notification = $this->createNotification(
            $student,
            $announcement,
        );

        $this->actingAs($student)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertViewIs('notifications.show')
            ->assertSee($announcement->title)
            ->assertSee($announcement->body);

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_user_cannot_view_another_users_notification(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $announcement = Announcement::factory()->create();

        $notification = $this->createNotification(
            $owner,
            $announcement,
        );

        $this->actingAs($otherStudent)
            ->get(route('notifications.show', $notification))
            ->assertNotFound();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_graduated_student_can_view_received_announcement(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $announcement = Announcement::factory()->create([
            'title' => '修了前に受信したお知らせ',
        ]);

        $notification = $this->createNotification(
            $student,
            $announcement,
        );

        $this->actingAs($student)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('修了前に受信したお知らせ');
    }

    public function test_guest_cannot_view_notification_detail(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $announcement = Announcement::factory()->create();

        $notification = $this->createNotification(
            $student,
            $announcement,
        );

        $this->get(route('notifications.show', $notification))
            ->assertRedirect('/login');
    }

    private function createNotification(
        User $user,
        Announcement $announcement,
    ): DatabaseNotification {
        $notificationId = (string) Str::uuid();

        return $user->notifications()->create([
            'id' => $notificationId,
            'type' => AdminAnnouncementNotification::class,
            'data' => [
                'notification_type' => 'admin_announcement',
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'message' => '運営からのお知らせが届きました。',
                'body' => $announcement->body,
                'url' => route(
                    'notifications.show',
                    $notificationId,
                ),
            ],
            'read_at' => null,
        ]);
    }
}
