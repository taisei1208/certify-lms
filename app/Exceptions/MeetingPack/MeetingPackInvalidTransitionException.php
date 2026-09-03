<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MeetingPackInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self(
            '下書きの面談パックのみ公開できます。',
        );
    }

    public static function forArchive(): self
    {
        return new self(
            '公開中の面談パックのみアーカイブできます。',
        );
    }

    public static function forUnarchive(): self
    {
        return new self(
            'アーカイブ中の面談パックのみ下書きに戻せます。',
        );
    }

    public static function forDelete(): self
    {
        return new self(
            '公開中の面談パックは削除できません。',
        );
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
