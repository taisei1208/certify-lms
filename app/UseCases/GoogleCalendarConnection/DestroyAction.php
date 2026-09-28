<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendarConnection;

use App\Models\User;
use App\Services\GoogleCalendarOAuthService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * コーチのGoogle Calendar連携を解除する。
 */
final class DestroyAction
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauthService,
    ) {}

    public function __invoke(User $coach): void
    {
        $connection = $coach->googleCredential()->first();

        if ($connection === null) {
            return;
        }

        try {
            $token = $connection->refresh_token
                ?? $connection->access_token;

            $this->oauthService->revokeToken($token);
        } catch (Throwable $exception) {
            Log::warning(
                'Google Calendarのトークン失効に失敗しました。',
                [
                    'coach_id' => $coach->id,
                    'exception' => $exception->getMessage(),
                ],
            );
        }

        $connection->delete();
    }
}
