<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlanInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self(
            '下書きのプランのみ公開できます。',
        );
    }

    public static function forArchive(): self
    {
        return new self(
            '公開中のプランのみアーカイブできます。',
        );
    }

    public static function forUnarchive(): self
    {
        return new self(
            'アーカイブ中のプランのみ下書きに戻せます。',
        );
    }

    public static function forUpdateUsers(): self
    {
        return new self(
            '契約中の受講生がいるプランは編集できません。',
        );
    }

    public static function forDeleteStatus(): self
    {
        return new self(
            '下書きのプランのみ削除できます。',
        );
    }

    public static function forDeleteUsers(): self
    {
        return new self(
            '受講者に割り当てられているプランは削除できません。',
        );
    }

    public static function forDeleteHistory(): self
    {
        return new self(
            'プラン履歴から参照されているプランは削除できません。',
        );
    }

    private function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
