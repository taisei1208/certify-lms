<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendarConnection;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarOAuthService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Google OAuthのコールバックを処理し、
 * コーチのGoogle Calendar連携を成立させる。
 */
final class CallbackAction
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauthService,
    ) {}

    public function __invoke(User $coach, string $authorizationCode): GoogleCalendarConnection
    {
        $token = $this->oauthService->exchangeAuthorizationCode($authorizationCode);

        return DB::transaction(
            function () use ($coach, $token): GoogleCalendarConnection {
                $connection = $coach->googleCredential()->firstOrNew();

                $refreshToken = $token['refresh_token'] ?? $connection->refresh_token;

                if ($refreshToken === '') {
                    throw new RuntimeException(
                        'Googleからリフレッシュトークンを取得できませんでした。'
                    );
                }

                if (! isset($token['access_token'], $token['expires_in'])) {
                    throw new RuntimeException(
                        'Googleからトークン情報を取得できませんでした。'
                    );
                }

                $connection->access_token = $token['access_token'];

                $connection->refresh_token = $refreshToken;

                $connection->token_expires_at = now()->addSeconds((int) $token['expires_in']);

                $connection->calendar_id = 'primary';
                $connection->connected_at = now();

                $connection->save();

                return $connection;
            },
        );
    }
}
