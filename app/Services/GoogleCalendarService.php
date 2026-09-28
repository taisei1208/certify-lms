<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Google\Service\Exception;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Google Calendar APIを利用した予定取得・登録・削除を提供する。
 */
class GoogleCalendarService
{
    private const CALENDAR_ID = 'primary';

    /**
     * 指定期間内の「予定あり」の時間帯を取得する。
     */
    public function busyPeriods(User $coach, Carbon $from, Carbon $to): Collection
    {
        $calendarService = $this->createAuthenticatedService($coach);

        $request = new FreeBusyRequest([
            'timeMin' => $from->toRfc3339String(),
            'timeMax' => $to->toRfc3339String(),
            'timeZone' => config('app.timezone'),
            'items' => [
                new FreeBusyRequestItem([
                    'id' => self::CALENDAR_ID,
                ]),
            ],
        ]);

        $response = $calendarService->freebusy->query($request);

        $calendar = $response
            ->getCalendars()[self::CALENDAR_ID]
            ?? null;

        if ($calendar === null) {
            return collect();
        }

        return collect($calendar->getBusy())
            ->map(fn ($period): array => [
                'start' => Carbon::parse($period->getStart()),
                'end' => Carbon::parse($period->getEnd()),
            ],
            );
    }

    /**
     * Google Calendarへ面談イベントを登録し、
     * 作成されたGoogleイベントIDを返す。
     */
    public function createMeetingEvent(User $coach, Meeting $meeting): string
    {
        $service = $this->createAuthenticatedService($coach);

        $meeting->loadMissing(['student', 'enrollment.certification']);

        $certificationName = $meeting->enrollment->certification->name;

        $description = implode("\n", array_filter([
            '資格: '.$certificationName,
            '受講生: '.$meeting->student->name,
            '面談テーマ: '.$meeting->topic,
            '面談URL: '.$meeting->meeting_url_snapshot,
        ]));

        $event = new Event([
            'summary' => $certificationName.' 面談',
            'description' => $description,
            'start' => [
                'dateTime' => $meeting->scheduled_at->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $meeting->scheduled_at->copy()->addHour()->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
        ]);

        $createdEvent = $service->events->insert(self::CALENDAR_ID, $event);

        $eventId = $createdEvent->getId();

        if (! is_string($eventId) || $eventId === '') {
            throw new RuntimeException(
                'Google CalendarのイベントIDを取得できませんでした。',
            );
        }

        return $eventId;
    }

    /**
     * Google Calendarから面談イベントを削除する。
     */
    public function deleteMeetingEvent(User $coach, string $eventId): void
    {
        try {
            $service = $this->createAuthenticatedService($coach);

            $service->events->delete(self::CALENDAR_ID, $eventId);
        } catch (Exception $exception) {
            if ($exception->getCode() === 404) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * コーチのOAuth情報から認証済みCalendar Serviceを生成する。
     */
    private function createAuthenticatedService(User $coach): Calendar
    {
        $connection = $coach->googleCredential()->first();

        if ($connection === null) {
            throw new RuntimeException('Google Calendarと連携されていません。');
        }

        $client = $this->createClient();

        if (
            $connection->token_expires_at === null
            || $connection->token_expires_at->lessThanOrEqualTo(now()->addMinute())
        ) {
            $this->refreshAccessToken($client, $connection);
        } else {
            /*
             * まだ有効なら、DBのアクセストークンをClientへ設定する。
             */
            $client->setAccessToken(['access_token' => $connection->access_token, 'expires_in' => 3600, 'created' => now()->timestamp,
            ]);
        }

        return new Calendar($client);
    }

    /**
     * リフレッシュトークンを使ってアクセストークンを更新する。
     */
    private function refreshAccessToken(Client $client, GoogleCalendarConnection $connection): void
    {
        if (
            $connection->refresh_token === null
        ) {
            throw new RuntimeException(
                'Googleのリフレッシュトークンがありません。',
            );
        }

        $token = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);

        if (isset($token['error']) || ! isset($token['access_token'])) {
            throw new RuntimeException(
                'Googleのアクセストークン更新に失敗しました。',
            );
        }

        $connection->access_token = $token['access_token'];

        if (isset($token['refresh_token']) && is_string($token['refresh_token'])) {
            $connection->refresh_token = $token['refresh_token'];
        }

        $connection->token_expires_at = now()->addSeconds((int) ($token['expires_in'] ?? 3600));

        $connection->save();
    }

    /**
     * Google Calendar APIクライアントを生成する。
     */
    private function createClient(): Client
    {
        $client = new Client;

        $client->setApplicationName((string) config('app.name'));

        $client->setClientId((string) config('services.google_calendar.client_id'));

        $client->setClientSecret((string) config('services.google_calendar.client_secret'));

        $client->setRedirectUri((string) config('services.google_calendar.redirect_uri'));

        $client->setScopes([
            Calendar::CALENDAR_EVENTS,
            'https://www.googleapis.com/auth/calendar.events.freebusy',
        ]);

        return $client;
    }
}
