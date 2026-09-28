<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google CalendarのOAuth認証処理を提供する。
 *
 * Google認可画面のURL生成、認可コードとトークンの交換、
 * Google側のトークン失効を担当する。
 */
final class GoogleCalendarOAuthService
{
    private const AUTHORIZATION_URL =
        'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL =
        'https://oauth2.googleapis.com/token';

    private const REVOKE_URL =
        'https://oauth2.googleapis.com/revoke';

    /**
     * Google OAuth認可画面のURLを生成する。
     */
    public function buildAuthorizationUrl(string $state): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config(
                'services.google_calendar.client_id',
            ),
            'redirect_uri' => config(
                'services.google_calendar.redirect_uri',
            ),
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/calendar.events',
                'https://www.googleapis.com/auth/calendar.events.freebusy',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return self::AUTHORIZATION_URL.'?'.$query;
    }

    /**
     * 認可コードをアクセストークンへ交換する。
     */
    public function exchangeAuthorizationCode(string $authorizationCode): array
    {
        $response = Http::asForm()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'redirect_uri' => config('services.google_calendar.redirect_uri'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Googleのトークン取得に失敗しました。');
        }

        $token = $response->json();

        if (! isset($token['access_token'])) {
            throw new RuntimeException('Googleからアクセストークンが返されませんでした。');
        }

        return $token;
    }

    /**
     * Google側でアクセストークンまたはリフレッシュトークンを失効させる。
     */
    public function revokeToken(string $token): void
    {
        $response = Http::asForm()
            ->post(self::REVOKE_URL, ['token' => $token]);

        if ($response->failed()) {
            throw new RuntimeException('Googleのトークン失効に失敗しました。');
        }
    }
}
