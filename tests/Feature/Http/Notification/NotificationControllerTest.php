<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\Notifications\ChatMessageReceiveNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_own_notifications(): void
    {
        $student = User::factory()->student()->create();

        $notification = $this->createNotification(
            $student,
            title: '表示される通知',
        );

        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertSee('表示される通知');
        $response->assertViewHas(
            'notifications',
            fn ($notifications) => $notifications
                ->contains('id', $notification->id),
        );
    }

    public function test_coach_can_view_own_notifications(): void
    {
        $coach = User::factory()->coach()->create();

        $this->createNotification(
            $coach,
            title: 'コーチ宛通知',
        );

        $this->actingAs($coach)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('コーチ宛通知');
    }

    public function test_other_users_notifications_are_not_displayed(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->createNotification(
            $student,
            title: '本人宛通知',
        );

        $this->createNotification(
            $otherStudent,
            title: '他人宛通知',
        );

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('本人宛通知')
            ->assertDontSee('他人宛通知');
    }

    public function test_notifications_are_displayed_in_newest_order(): void
    {
        $student = User::factory()->student()->create();

        $old = $this->createNotification(
            $student,
            title: '古い通知',
            createdAt: now()->subDay(),
        );

        $new = $this->createNotification(
            $student,
            title: '新しい通知',
            createdAt: now(),
        );

        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewHas(
            'notifications',
            function ($notifications) use ($old, $new): bool {
                return $notifications->getCollection()
                    ->pluck('id')
                    ->values()
                    ->all() === [
                        $new->id,
                        $old->id,
                    ];
            },
        );
    }

    public function test_notifications_are_paginated_by_twenty(): void
    {
        $student = User::factory()->student()->create();

        foreach (range(1, 21) as $index) {
            $this->createNotification(
                $student,
                title: '通知'.$index,
                createdAt: now()->subMinutes($index),
            );
        }

        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewHas(
            'notifications',
            fn ($notifications) => $notifications->count() === 20
                && $notifications->total() === 21
                && $notifications->lastPage() === 2,
        );
    }

    public function test_unread_tab_displays_only_unread_notifications(): void
    {
        $student = User::factory()->student()->create();

        $this->createNotification(
            $student,
            title: '未読通知',
        );

        $this->createNotification(
            $student,
            title: '既読通知',
            readAt: now(),
        );

        $this->actingAs($student)
            ->get(route('notifications.index', [
                'tab' => 'unread',
            ]))
            ->assertOk()
            ->assertViewHas('tab', 'unread')
            ->assertViewHas('unreadCount', 1)
            ->assertSee('未読通知')
            ->assertDontSee('既読通知');
    }

    public function test_all_tab_displays_read_and_unread_notifications(): void
    {
        $student = User::factory()->student()->create();

        $this->createNotification(
            $student,
            title: '未読通知',
        );

        $this->createNotification(
            $student,
            title: '既読通知',
            readAt: now(),
        );

        $this->actingAs($student)
            ->get(route('notifications.index', [
                'tab' => 'all',
            ]))
            ->assertOk()
            ->assertViewHas('tab', 'all')
            ->assertViewHas('unreadCount', 1)
            ->assertSee('未読通知')
            ->assertSee('既読通知');
    }

    public function test_invalid_tab_is_rejected(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('notifications.index', [
                'tab' => 'invalid',
            ]))
            ->assertSessionHasErrors('tab');
    }

    public function test_clicking_notification_marks_it_as_read_and_redirects(): void
    {
        $student = User::factory()->student()->create();

        $notification = $this->createNotification(
            $student,
            title: '未読通知',
            url: route('notifications.index'),
        );

        $this->assertNull($notification->read_at);

        $this->actingAs($student)
            ->post(
                route(
                    'notifications.markAsRead',
                    $notification,
                ),
            )
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_already_read_notification_can_be_opened(): void
    {
        $student = User::factory()->student()->create();

        $notification = $this->createNotification(
            $student,
            title: '既読通知',
            url: route('notifications.index'),
            readAt: now()->subHour(),
        );

        $originalReadAt = $notification->read_at;

        $this->actingAs($student)
            ->post(
                route(
                    'notifications.markAsRead',
                    $notification,
                ),
            )
            ->assertRedirect(route('notifications.index'));

        $this->assertTrue(
            $notification->fresh()->read_at->equalTo(
                $originalReadAt,
            ),
        );
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $notification = $this->createNotification(
            $otherStudent,
            title: '他人宛通知',
        );

        $this->actingAs($student)
            ->post(
                route(
                    'notifications.markAsRead',
                    $notification,
                ),
            )
            ->assertNotFound();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_mark_all_as_read_marks_only_authenticated_users_notifications(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $ownFirst = $this->createNotification(
            $student,
            title: '本人通知1',
        );

        $ownSecond = $this->createNotification(
            $student,
            title: '本人通知2',
        );

        $other = $this->createNotification(
            $otherStudent,
            title: '他人通知',
        );

        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas(
                'success',
                'すべての通知を既読にしました。',
            );

        $this->assertNotNull($ownFirst->fresh()->read_at);
        $this->assertNotNull($ownSecond->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_mark_all_as_read_keeps_existing_read_timestamp(): void
    {
        $student = User::factory()->student()->create();

        $existingReadAt = now()->subDay()->startOfSecond();

        $alreadyRead = $this->createNotification(
            $student,
            title: '既読通知',
            readAt: $existingReadAt,
        );

        $this->createNotification(
            $student,
            title: '未読通知',
        );

        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'));

        $this->assertTrue(
            $alreadyRead->fresh()->read_at->equalTo(
                $existingReadAt,
            ),
        );
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect();

        $this->post(route('notifications.markAllAsRead'))
            ->assertRedirect();
    }

    public function test_admin_cannot_access_notifications(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertForbidden();
    }

    public function test_graduated_student_can_view_existing_notifications(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $this->createNotification(
            $student,
            title: '修了前に受信した通知',
        );

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('修了前に受信した通知');
    }

    private function createNotification(
        User $recipient,
        string $title,
        ?string $url = null,
        mixed $readAt = null,
        mixed $createdAt = null,
    ): DatabaseNotification {
        $createdAt ??= now();

        /** @var DatabaseNotification $notification */
        $notification = $recipient->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ChatMessageReceiveNotification::class,
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => $title,
                'message' => $title.'の本文です。',
                'url' => $url ?? route('notifications.index'),
            ],
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $notification;
    }
}
