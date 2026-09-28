<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendarConnection;

use App\Services\GoogleCalendarOAuthService;

/**
 * Google OAuth認可画面のURLを生成する。
 */
final class ConnectAction
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauthService,
    ) {}

    public function __invoke(string $state): string
    {
        return $this->oauthService
            ->buildAuthorizationUrl($state);
    }
}
