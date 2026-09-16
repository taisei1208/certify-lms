<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\MeetingReminderType;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_uses_mail_and_database_channels(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::Eve,
        );

        $this->assertSame(
            [
                'mail',
                'database',
            ],
            $notification->via($student),
        );
    }

    public function test_eve_notification_contains_expected_data(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->create([
                'scheduled_at' => '2026-09-17 10:00:00',
            ]);

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::Eve,
        );

        $data = $notification->toArray($student);

        $this->assertSame(
            'meeting_reminder',
            $data['notification_type'],
        );

        $this->assertSame(
            $meeting->id,
            $data['meeting_id'],
        );

        $this->assertSame(
            MeetingReminderType::Eve->value,
            $data['reminder_type'],
        );

        $this->assertSame(
            '明日の面談のお知らせ',
            $data['title'],
        );

        $this->assertStringContainsString(
            '2026/09/17 10:00',
            $data['message'],
        );

        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url'],
        );
    }

    public function test_one_hour_before_notification_contains_expected_data(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->create([
                'scheduled_at' => '2026-09-17 10:00:00',
            ]);

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderType::OneHourBefore,
        );

        $data = $notification->toArray($student);

        $this->assertSame(
            MeetingReminderType::OneHourBefore->value,
            $data['reminder_type'],
        );

        $this->assertSame(
            '1時間後の面談のお知らせ',
            $data['title'],
        );

        $this->assertStringContainsString(
            '面談が予定されています',
            $data['message'],
        );
    }
}
