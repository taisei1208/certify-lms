<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\MeetingReminderType;
use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_eve_reminder_is_sent_to_student_and_coach(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        [$meeting, $student, $coach] = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-17 10:00:00',
            ),
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput(
                '前日リマインダーを2件配信しました。',
            )
            ->assertSuccessful();

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (
                MeetingReminderNotification $notification,
            ) use ($student): bool {
                return $notification
                    ->toArray($student)['reminder_type']
                    === MeetingReminderType::Eve->value;
            },
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $student->id,
                'reminder_type' => MeetingReminderType::Eve->value,
            ],
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $coach->id,
                'reminder_type' => MeetingReminderType::Eve->value,
            ],
        );

        $this->assertDatabaseCount(
            'meeting_reminder_deliveries',
            2,
        );
    }

    public function test_one_hour_before_reminder_is_sent_to_student_and_coach(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 14:30:15',
        );

        [$meeting, $student, $coach] = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-16 15:30:00',
            ),
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'one_hour_before'],
        )
            ->expectsOutput(
                '1時間前リマインダーを2件配信しました。',
            )
            ->assertSuccessful();

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (
                MeetingReminderNotification $notification,
            ) use ($student): bool {
                return $notification
                    ->toArray($student)['reminder_type']
                    === MeetingReminderType::OneHourBefore->value;
            },
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $student->id,
                'reminder_type' => MeetingReminderType::OneHourBefore->value,
            ],
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $coach->id,
                'reminder_type' => MeetingReminderType::OneHourBefore->value,
            ],
        );
    }

    public function test_same_reminder_is_not_sent_twice(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        [$meeting, $student, $coach] = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-17 10:00:00',
            ),
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertSuccessful();

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput(
                '前日リマインダーを0件配信しました。',
            )
            ->assertSuccessful();

        $this->assertCount(
            1,
            Notification::sent(
                $student,
                MeetingReminderNotification::class,
            ),
        );

        $this->assertCount(
            1,
            Notification::sent(
                $coach,
                MeetingReminderNotification::class,
            ),
        );

        $this->assertDatabaseCount(
            'meeting_reminder_deliveries',
            2,
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $student->id,
                'reminder_type' => MeetingReminderType::Eve->value,
            ],
        );
    }

    public function test_eve_and_one_hour_before_are_sent_separately(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        [$meeting, $student, $coach] = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-17 10:00:00',
            ),
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertSuccessful();

        CarbonImmutable::setTestNow(
            '2026-09-17 09:00:00',
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'one_hour_before'],
        )->assertSuccessful();

        $this->assertCount(
            2,
            Notification::sent(
                $student,
                MeetingReminderNotification::class,
            ),
        );

        $this->assertCount(
            2,
            Notification::sent(
                $coach,
                MeetingReminderNotification::class,
            ),
        );

        $this->assertDatabaseCount(
            'meeting_reminder_deliveries',
            4,
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $student->id,
                'reminder_type' => MeetingReminderType::Eve->value,
            ],
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $student->id,
                'reminder_type' => MeetingReminderType::OneHourBefore->value,
            ],
        );
    }

    public function test_meeting_outside_target_period_is_not_notified(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        // 翌日ではなく2日後
        $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-18 10:00:00',
            ),
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput(
                '前日リマインダーを0件配信しました。',
            )
            ->assertSuccessful();

        Notification::assertNothingSent();

        $this->assertDatabaseCount(
            'meeting_reminder_deliveries',
            0,
        );
    }

    public function test_canceled_meeting_is_not_notified(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        [$meeting] = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-17 10:00:00',
            ),
        );

        $meeting->update([
            'status' => MeetingStatus::Canceled,
            'canceled_at' => now(),
        ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertSuccessful();

        Notification::assertNothingSent();

        $this->assertDatabaseCount(
            'meeting_reminder_deliveries',
            0,
        );
    }

    public function test_completed_meeting_is_not_notified(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        [$meeting] = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-17 10:00:00',
            ),
        );

        $meeting->update([
            'status' => MeetingStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertSuccessful();

        Notification::assertNothingSent();

        $this->assertDatabaseCount(
            'meeting_reminder_deliveries',
            0,
        );
    }

    public function test_graduated_student_is_excluded_but_active_coach_is_notified(): void
    {
        Notification::fake();

        CarbonImmutable::setTestNow(
            '2026-09-16 18:00:00',
        );

        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = $this->createMeeting(
            CarbonImmutable::parse(
                '2026-09-17 10:00:00',
            ),
            $student,
            $coach,
        )[0];

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput(
                '前日リマインダーを1件配信しました。',
            )
            ->assertSuccessful();

        Notification::assertNotSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );

        $this->assertDatabaseMissing(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $student->id,
            ],
        );

        $this->assertDatabaseHas(
            'meeting_reminder_deliveries',
            [
                'meeting_id' => $meeting->id,
                'user_id' => $coach->id,
            ],
        );
    }

    public function test_command_fails_when_window_is_missing(): void
    {
        $this->artisan(
            'notifications:send-meeting-reminders',
        )
            ->expectsOutput(
                '--windowにはeveまたはone_hour_beforeを指定してください。',
            )
            ->assertFailed();
    }

    public function test_command_fails_when_window_is_invalid(): void
    {
        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'invalid'],
        )
            ->expectsOutput(
                '不正なwindowです: invalid。eveまたはone_hour_beforeを指定してください。',
            )
            ->assertFailed();
    }

    /**
     * @return array{0: Meeting, 1: User, 2: User}
     */
    private function createMeeting(
        CarbonImmutable $scheduledAt,
        ?User $student = null,
        ?User $coach = null,
    ): array {
        $student ??= User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach ??= User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->learning()
            ->for($student, 'user')
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
            ]);

        return [
            $meeting,
            $student,
            $coach,
        ];
    }
}
