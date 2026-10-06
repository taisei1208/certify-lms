<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Google\Service\Calendar;
use Google\Service\Calendar\Resource\Events;
use Google\Service\Exception as GoogleServiceException;
use App\Models\GoogleCalendarConnection;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use RuntimeException;

class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_delete_treats_missing_google_event_as_success(): void
    {
        $coach = new User(['name' => 'テストコーチ']);

        $events = Mockery::mock(Events::class);

        $events->shouldReceive('delete')->once()
            ->with('primary','already-deleted-event')
            ->andThrow(
                new GoogleServiceException('Event not found.', 404)
            );

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $events;

        $service = new class($calendar) extends GoogleCalendarService
        {
            public function __construct(
                private readonly Calendar $calendar,
            ) {}

            protected function createAuthenticatedService(
                User $coach,
            ): Calendar {
                return $this->calendar;
            }
        };

        /*
         * Google側ですでに削除済みの場合は、
         * 例外を外へ投げず成功扱いにする。
         */
        $service->deleteMeetingEvent($coach, 'already-deleted-event');

        $this->addToAssertionCount(1);
    }

    public function test_expired_access_token_is_refreshed(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $connection = GoogleCalendarConnection::factory()->for($coach, 'user')->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'old-refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $client = new class extends Client
        {
            public ?string $receivedRefreshToken = null;

            public function fetchAccessTokenWithRefreshToken(
                $refreshToken = null,
            ) {
                $this->receivedRefreshToken = $refreshToken;

                return [
                    'access_token' => 'refreshed-access-token',
                    'refresh_token' => 'rotated-refresh-token',
                    'expires_in' => 3600,
                ];
            }
        };

        $service = new class($client) extends GoogleCalendarService
        {
            public function __construct(
                private readonly Client $client,
            ) {}

            public function authenticate(User $coach): Calendar
            {
                return $this->createAuthenticatedService($coach);
            }

            protected function createClient(): Client
            {
                return $this->client;
            }
        };

        $service->authenticate($coach);

        $connection->refresh();

        $this->assertSame(
            'old-refresh-token',
            $client->receivedRefreshToken,
        );

        $this->assertSame(
            'refreshed-access-token',
            $connection->access_token,
        );

        $this->assertSame(
            'rotated-refresh-token',
            $connection->refresh_token,
        );

        $this->assertTrue(
            $connection->token_expires_at->isFuture(),
        );
    }

    public function test_refresh_failure_keeps_existing_tokens(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $connection = GoogleCalendarConnection::factory()->for($coach, 'user')->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'invalid-refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $client = new class extends Client
        {
            public ?string $receivedRefreshToken = null;

            public function fetchAccessTokenWithRefreshToken(
                $refreshToken = null,
            ) {
                $this->receivedRefreshToken = $refreshToken;

                return [
                    'error' => 'invalid_grant',
                    'error_description' => 'Refresh token is invalid.'
                ];
            }
        };

        $service = new class($client) extends GoogleCalendarService
        {
            public function __construct(
                private readonly Client $client,
            ) {}

            public function authenticate(User $coach): Calendar
            {
                return $this->createAuthenticatedService($coach);
            }

            protected function createClient(): Client
            {
                return $this->client;
            }
        };

        try {
            $service->authenticate($coach);

            $this->fail('トークン更新失敗時に例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame('Googleのアクセストークン更新に失敗しました。',
            $exception->getMessage(),
            );
        }

        $connection->refresh();

        $this->assertSame(
            'invalid-refresh-token',
            $client->receivedRefreshToken,
        );

        $this->assertSame(
            'expired-access-token',
            $connection->access_token,
        );

        $this->assertSame(
            'invalid-refresh-token',
            $connection->refresh_token,
        );

        $this->assertTrue(
            $connection->token_expires_at->isPast(),
        );
    }
}