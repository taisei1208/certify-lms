<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCalendarConnection;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class MeetingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_student_index_lists_only_own_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $response = $this->actingAs($student)->get(route('meetings.index'));

        $response->assertOk();
        $response->assertViewIs('meeting.index');
        $response->assertViewHas('meetings', fn ($meetings) => $meetings->contains('id', $own->id)
            && ! $meetings->contains('id', $other->id));
    }

    public function test_show_blocks_third_party(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thirdParty = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $response = $this->actingAs($thirdParty)->get(route('meetings.show', $meeting));

        $response->assertForbidden();
    }

    public function test_show_allows_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $this->actingAs($student)->get(route('meetings.show', $meeting))->assertOk();
        $this->actingAs($coach)->get(route('meetings.show', $meeting))->assertOk();
    }

    public function test_create_requires_enrollment_ownership(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        $foreignEnrollment = Enrollment::factory()->for($other, 'user')->for($certification)->learning()->create();

        $response = $this->actingAs($student)->get(route('meetings.create', $foreignEnrollment));

        $response->assertForbidden();
    }

    public function test_store_creates_meeting_for_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
        ]);
    }

    public function test_store_rejects_non_zero_minutes(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 30); // 次の月曜 10:30(分が 0 でない=不正)
        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertSessionHasErrors('scheduled_at');
        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_cancel_blocks_third_party(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thirdParty = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $response = $this->actingAs($thirdParty)->post(route('meetings.cancel', $meeting));

        $response->assertForbidden();
        $this->assertSame(MeetingStatus::Reserved, $meeting->fresh()->status);
    }

    public function test_cancel_allows_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $response = $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        $response->assertRedirect();
        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
    }

    public function test_index_as_coach_only_lists_own_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $response = $this->actingAs($coach)->get(route('coach.meetings.index'));

        $response->assertOk();
        $response->assertViewIs('meeting.coach.index');
        $response->assertViewHas('meetings', fn ($meetings) => $meetings->contains('id', $own->id)
            && ! $meetings->contains('id', $other->id));
    }

    public function test_upsert_memo_only_for_assigned_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $response = $this->actingAs($otherCoach)->put(route('coach.meetings.memo', $meeting), [
            'body' => '他人のメモを書く試み',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('meeting_memos', ['meeting_id' => $meeting->id]);
    }

    public function test_upsert_memo_succeeds_for_assigned_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $response = $this->actingAs($coach)->put(route('coach.meetings.memo', $meeting), [
            'body' => '初回面談メモ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '初回面談メモ',
        ]);
    }

    public function test_fetch_availability_returns_json_slots(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $date = now()->startOfDay()->next(Carbon::MONDAY)->format('Y-m-d'); // 次の月曜(未来)
        $response = $this->actingAs($student)->getJson(
            route('meetings.availability', $enrollment)."?date={$date}"
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'date',
            'slots' => [
                '*' => ['slot_start', 'slot_end', 'available_coach_count'],
            ],
        ]);
        $this->assertCount(3, $response->json('slots'));
    }

    public function test_graduated_student_cannot_access_create(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $response = $this->actingAs($student)->get(route('meetings.create', $enrollment));

        $response->assertForbidden();
    }

    public function test_store_blocks_double_booking_for_same_coach_and_slot(): void
    {
        // Arrange: 予約可能コンテキスト + 同コーチ・同時刻に canceled 面談を 1 件先在させる。
        // canceled は候補抽出(予約済コーチ除外)をすり抜けるが、(coach_id, scheduled_at) UNIQUE は
        // status を問わず効くため、並行を起こさず決定論的に二重予約の衝突を再現できる。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $otherStudent = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        Meeting::factory()->canceled()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => $scheduledAt,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->from(route('meetings.create', $enrollment))
            ->post(route('meetings.store', $enrollment), [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '相談したい',
            ]);

        // Assert: 二重予約は成立せず、新規 reserved は作られない(canceled の 1 件のみが残る)
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(
            0,
            Meeting::query()->where('status', MeetingStatus::Reserved->value)->count(),
            '同コーチ・同時刻の二重予約は (coach_id, scheduled_at) UNIQUE で阻止されるはず',
        );
    }

    public function test_cancel_refunds_meeting_quota(): void
    {
        // Arrange: 予約済(残数消費済)面談 1 件。キャンセルで返却記録が作られることを確認する。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        // Act
        $response = $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        // Assert: キャンセル成立 + 消費分 1 回が返却記録として作られる
        $response->assertRedirect();
        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }

    public function test_meeting_reserve_notifies_coach(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0); // 次の月曜 10:00(未来)

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            function (
                MeetingReservedNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $student,
            MeetingReservedNotification::class,
        );
    }

    public function test_meeting_cancel_notifies_(): void
    {
        Notification::fake();

        $canceler = User::factory()->student()->create();
        $recipient = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($recipient)->forStudent($canceler)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->actingAs($canceler)->post(route('meetings.cancel', $meeting));

        Notification::assertSentTo(
            $recipient,
            MeetingCanceledNotification::class,
            function (
                MeetingCanceledNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $canceler,
            MeetingCanceledNotification::class,
        );
    }

    public function test_store_creates_google_calendar_event_for_connected_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->inProgress()->create(['meeting_url' => 'https://meet.example.com/coach-room']);

        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('busyPeriods')->andReturn(collect());

                $mock->shouldReceive('createMeetingEvent')->once()->andReturn('google-event-123');
            },
        );

        $response = $this->actingAs($student)->post(
            route('meetings.store', $enrollment),
            [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => 'Googleイベント登録確認',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
            'google_calendar_event_id' => 'google-event-123',
        ]);
    }

    public function test_google_event_creation_failure_does_not_cancel_meeting_reservation(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->inProgress()->create(['meeting_url' => 'https://meet.example.com/coach-room']);

        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('busyPeriods')->andReturn(collect());

                $mock->shouldReceive('createMeetingEvent')->once()->andThrow(new RuntimeException(
                    'Google Calendar API error',
                ),
                );
            },
        );

        $response = $this->actingAs($student)->post(
            route('meetings.store', $enrollment),
            [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => 'Google登録失敗確認',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Reserved->value,
            'google_calendar_event_id' => null,
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
        ]);
    }

    public function test_cancel_deletes_google_calendar_event(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);

        $coach = User::factory()->coach()->inProgress()->create(['meeting_url' => 'https://meet.example.com/coach-room']);

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_calendar_event_id' => 'google-event-123',
        ]);

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock) use ($coach): void {
                $mock->shouldReceive('deleteMeetingEvent')->once()
                    ->withArgs(
                        fn (User $actualCoach, string $eventId): bool => $actualCoach->is($coach) && $eventId === 'google-event-123',
                    );
            },
        );

        $response = $this->actingAs($student)->post(
            route('meetings.cancel', $meeting)
        );

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('meetings', [
            'student_id' => $student->id,
            'status' => MeetingStatus::Canceled->value,
            'google_calendar_event_id' => null,
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }

    public function test_availability_excludes_slots_overlapping_google_busy_period(): void
    {
        $this->withoutExceptionHandling();
        $student = User::factory()->student()->inProgress()->create();

        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '12:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $date = now()->startOfDay()->next(Carbon::MONDAY);

        /*
         * 10:30〜11:30なので、
         * 10:00枠と11:00枠の両方に重なる。
         */
        $busyStart = $date->copy()->setTime(10, 30);
        $busyEnd = $date->copy()->setTime(11, 30);

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock) use ($busyStart, $busyEnd): void {
                $mock->shouldReceive('busyPeriods')
                    ->andReturn(collect([
                        [
                            'start' => $busyStart,
                            'end' => $busyEnd,
                        ],
                    ]));
            },
        );

        $response = $this->actingAs($student)->getJson(route('meetings.availability', $enrollment).'?date='.$date->format('Y-m-d'));

        $response->assertOk();

        $slotStarts = collect($response->json('slots'))->pluck('slot_start')
            ->map(
                fn (string $start): string => Carbon::parse($start)->format('H:i')
            );

        $this->assertContains('09:00', $slotStarts);
        $this->assertNotContains('10:00', $slotStarts);
        $this->assertNotContains('11:00', $slotStarts);
    }

    public function test_availability_does_not_exclude_free_google_event(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '12:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('busyPeriods')
                    ->andReturn(collect());
            },
        );

        $date = now()->startOfDay()->next(Carbon::MONDAY)->format('Y-m-d');

        $response = $this->actingAs($student)->getJson(route('meetings.availability', $enrollment)."?date={$date}");

        $response->assertOk();

        $this->assertCount(3, $response->json('slots'));
    }

    public function test_availability_keeps_slot_when_one_coach_is_free(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $admin = User::factory()->admin()->create();

        $busyCoach = User::factory()->coach()->inProgress()->create();
        $freeCoach = User::factory()->coach()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $busyCoach, $admin);
        $this->attachCoach($certification, $freeCoach, $admin);

        foreach ([$busyCoach, $freeCoach] as $coach) {
            CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '12:00:00')->create();

            GoogleCalendarConnection::factory()->for($coach, 'user')->create();
        }

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $date = now()->startOfDay()->next(Carbon::MONDAY);

        $busyStart = $date->copy()->setTime(10, 0);
        $busyEnd = $date->copy()->setTime(11, 0);

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock) use ($busyCoach, $busyStart, $busyEnd): void {
                $mock->shouldReceive('busyPeriods')
                    ->andReturnUsing(function (User $coach) use ($busyCoach, $busyStart, $busyEnd) {
                        if ($coach->is($busyCoach)) {
                            return collect([
                                [
                                    'start' => $busyStart,
                                    'end' => $busyEnd,
                                ],
                            ]);
                        }

                        return collect();
                    });
            },
        );

        $response = $this->actingAs($student)->getJson(route('meetings.availability', $enrollment).'?date='.$date->format('Y-m-d'));

        $response->assertOk();

        $slot = collect($response->json('slots'))->first(
            fn (array $slot): bool => Carbon::parse($slot['slot_start'])->format('H:i') === '10:00',
        );

        $this->assertNotNull($slot);
        $this->assertSame(1, $slot['available_coach_count']);
    }

    public function test_availability_falls_back_when_google_api_fails(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $admin = User::factory()->admin()->create();

        $coach = User::factory()->coach()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '12:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('busyPeriods')
                    ->andThrow(new RuntimeException(
                        'Google Calendar API error',
                    ),
                    );
            },
        );

        $date = now()->startOfDay()->next(Carbon::MONDAY)->format('Y-m-d');

        $response = $this->actingAs($student)->getJson(route('meetings.availability', $enrollment)."?date={$date}");

        $response->assertOk();

        $this->assertCount(3, $response->json('slots'));
    }

    public function test_cancel_succeeds_when_google_event_deletion_fails(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);

        $coach = User::factory()->coach()->inProgress()->create();

        GoogleCalendarConnection::factory()->for($coach, 'user')->create();

        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_calendar_event_id' => 'google-event-delete-failed',
        ]);

        $this->mock(GoogleCalendarService::class,
            function (MockInterface $mock) use (
                $coach,
            ): void {
                $mock->shouldReceive('deleteMeetingEvent')->once()->withArgs(
                    fn (
                        User $actualCoach,
                        string $eventId,
                    ): bool => $actualCoach->is($coach)
                        && $eventId
                        === 'google-event-delete-failed',
                )
                    ->andThrow(
                        new RuntimeException('Google Calendar API error')
                    );
            }
        );

        $response = $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        $response->assertRedirect()->assertSessionMissing('error');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
            'google_calendar_event_id' => 'google-event-delete-failed',
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions',
            [
                'user_id' => $student->id,
                'related_meeting_id' => $meeting->id,
                'type' => MeetingQuotaTransactionType::Refunded->value,
                'amount' => 1,
            ]
        );
    }
}
