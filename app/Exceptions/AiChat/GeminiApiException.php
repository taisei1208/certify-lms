<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;
use Throwable;

/**
 * Gemini APIとの通信または応答処理に失敗した場合の例外。
 */
class GeminiApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $upstreamStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function missingApiKey(): self
    {
        return new self(
            'Gemini APIキーが設定されていません。',
        );
    }

    public static function invalidResponse(): self
    {
        return new self(
            'Gemini APIから有効な回答を取得できませんでした。',
        );
    }

    public static function requestFailed(int $status, string $detail): self
    {
        return new self(
            "Gemini API request failed ({$status}): {$detail}",
            $status,
        );
    }
}
