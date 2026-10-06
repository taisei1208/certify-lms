<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_calendar.client_id' => 'test-client-id',
            'services.google_calendar.client_secret' => 'test-client-secret',
            'services.google_calendar.redirect_uri' => 'http://localhost/settings/google-calendar/callback',
        ]);
    }

    public function test_coach_can_start_google_calendar_connection(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.redirect'),
        );

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringContainsString(
            'accounts.google.com',
            $location,
        );

        $this->assertNotNull(
            session('google_calendar_oauth_state'),
        );
    }

    public function test_student_cannot_start_google_calendar_connection(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $this->actingAs($student)
            ->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }

    public function test_admin_cannot_start_google_calendar_connection(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }

    public function test_valid_state_saves_google_calendar_tokens(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'refresh_token' => 'test-refresh-token',
                'scope' => implode(' ', [
                    'https://www.googleapis.com/auth/calendar.events',
                    'https://www.googleapis.com/auth/calendar.events.freebusy',
                ]),
                'token_type' => 'Bearer',
            ]),
        ]);

        $response = $this
            ->withSession([
                'google_calendar_oauth_state' => 'valid-state',
            ])
            ->actingAs($coach)
            ->get(route('settings.google-calendar.callback', [
                'state' => 'valid-state',
                'code' => 'authorization-code',
            ]));

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'google_calendar_connections',
            [
                'user_id' => $coach->id,
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'calendar_id' => 'primary',
            ],
        );
    }

    public function test_invalid_state_is_rejected(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        Http::fake();

        $response = $this
            ->withSession([
                'google_calendar_oauth_state' => 'expected-state',
            ])
            ->actingAs($coach)
            ->get(route('settings.google-calendar.callback', [
                'state' => 'invalid-state',
                'code' => 'authorization-code',
            ]));

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'google_calendar_connections',
            [
                'user_id' => $coach->id,
            ],
        );

        Http::assertNothingSent();
    }

    public function test_authorization_denial_does_not_save_connection(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        Http::fake();

        $response = $this
            ->withSession([
                'google_calendar_oauth_state' => 'valid-state',
            ])
            ->actingAs($coach)
            ->get(route('settings.google-calendar.callback', [
                'state' => 'valid-state',
                'error' => 'access_denied',
            ]));

        $response->assertRedirect();

        $this->assertDatabaseMissing(
            'google_calendar_connections',
            [
                'user_id' => $coach->id,
            ],
        );

        Http::assertNothingSent();
    }

    public function test_disconnect_deletes_google_calendar_connection(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $connection = GoogleCalendarConnection::factory()
            ->for($coach, 'user')
            ->create();

        Http::fake([
            'https://oauth2.googleapis.com/revoke*' => Http::response(status: 200),
        ]);

        $response = $this->actingAs($coach)->delete(
            route('settings.google-calendar.destroy'),
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing(
            'google_calendar_connections',
            [
                'id' => $connection->id,
            ],
        );
    }

    public function test_token_exchange_failure_does_not_save_connection(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        Http::fake([
            'https://oauth2.googleapis.com/token' =>
                Http::response([
                    'error' => 'invalid_grant',
                    'error_description' =>'Authorization code is invalid.',
                ], 400),
        ]);

        $response = $this->withSession([
            'google_calendar_oauth_state' =>'valid-state',
        ])
        ->actingAs($coach)
        ->get(
            route(
                'settings.google-calendar.callback',
                [
                    'state' => 'valid-state',
                    'code' => 'invalid-authorization-code',
                ],
            ),
        );

        $response->assertRedirect(
            route('settings.availability.index'),
        )
        ->assertSessionHas(
            'error', 'Google Calendarとの連携に失敗しました。',
        );

        $this->assertDatabaseMissing(
            'google_calendar_connections',
            ['user_id' => $coach->id]
        );

        Http::assertSentCount(1);

        Http::assertSent(
            fn ($request): bool => $request->url()
            === 'https://oauth2.googleapis.com/token'
            && $request['code'] === 'invalid-authorization-code'
        );
    }
    public function test_disconnect_deletes_connection_when_revoke_fails(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $connection = GoogleCalendarConnection::factory()
            ->for($coach, 'user')
            ->create([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
            ]);

        Http::fake([
            'https://oauth2.googleapis.com/revoke*' =>Http::response([
                'error' => 'server_error',
            ], 500),
        ]);

        $response = $this->actingAs($coach)->delete(
            route('settings.google-calendar.destroy'),
        );

        $response->assertRedirect(route('settings.availability.index'))
            ->assertSessionHas(
                'success', 'Google Calendarとの連携を解除しました。'
            );

        $this->assertDatabaseMissing(
            'google_calendar_connections',
            ['id' => $connection->id]
        );

        Http::assertSentCount(1);

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://oauth2.googleapis.com/revoke'
            && $request['token']
            === 'test-refresh-token',
        );
    }
}
