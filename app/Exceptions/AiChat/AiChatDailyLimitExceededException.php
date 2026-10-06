<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;

class AiChatDailyLimitExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            '本日のAI相談回数の上限に達しました。',
        );
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 429);
    }
}
