<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用のデータベース通知を作成する。
     */
    private function createNotification(User $user, string $title, ?Carbon $createdAt = null, ?Carbon $readAt = null): object
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Fixtures\\TestNotification',
            'data' => [
                'title' => $title,
                'message' => "{$title}の本文です。",
                'url' => route('notifications.index'),
            ],
            'read_at' => $readAt,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);
    }

    public function test_guest_cannot_get_notifications(): void
    {
        $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
    }

    public function test_student_gets_only_latest_twenty_own_notifications(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');

        $student = User::factory()->student()->create();

        $otherStudent = User::factory()->student()->create();

        $notificationIds = [];

        for ($index = 0; $index < 25; $index++) {
            $notificationIds[] = $this->createNotification(
                $student,
                "本人宛の通知{$index}",
                now()->subMinutes($index),
            )->id;
        }

        $this->createNotification(
            $otherStudent,
            '他人宛の通知',
            now()->addMinute(),
        );

        $response = $this->actingAs($student)
            ->getJson(
                route('api.v1.notifications.index', ['tab' => 'all']),
            );

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.tab', 'all')
            ->assertJsonPath('meta.unread_count', 25)
            ->assertJsonPath('data.0.id', $notificationIds[0])
            ->assertJsonPath('data.19.id', $notificationIds[19])
            ->assertJsonMissing([
                'title' => '他人宛の通知',
            ]);
    }

    public function test_unread_tab_returns_only_unread_notifications(): void
    {
        $student = User::factory()->student()->create();

        $unread = $this->createNotification($student, '未読通知');

        $this->createNotification($student, '既読通知', readAt: now());

        $this->actingAs($student)
            ->getJson(
                route('api.v1.notifications.index', ['tab' => 'unread']),
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonMissing([
                'title' => '既読通知',
            ]);
    }

    public function test_invalid_tab_returns_validation_error(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->getJson(
                route('api.v1.notifications.index', ['tab' => 'invalid']),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tab');
    }

    public function test_student_can_mark_own_notification_as_read(): void
    {
        $student = User::factory()->student()->create();

        $notification = $this->createNotification($student, 'テスト通知');

        $this->actingAs($student)
            ->postJson(
                route('api.v1.notifications.read', ['notification' => $notification->id])
            )
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_student_cannot_mark_another_users_notification_as_read(): void
    {
        $student = User::factory()->student()->create();

        $otherStudent = User::factory()->student()->create();

        $notification = $this->createNotification($otherStudent, '他人宛の通知');

        $this->actingAs($student)
            ->postJson(
                route('api.v1.notifications.read', ['notification' => $notification->id])
            )
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_student_can_mark_all_own_notifications_as_read(): void
    {
        $student = User::factory()->student()->create();

        $otherStudent = User::factory()->student()->create();

        $first = $this->createNotification($student, '一つ目の通知');
        $second = $this->createNotification($student, '二つ目の通知');
        $other = $this->createNotification($otherStudent, '他人宛の通知');

        $this->actingAs($student)
            ->postJson(route('api.v1.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);

        // 他人の通知は更新されない
        $this->assertNull($other->fresh()->read_at);
    }
}
