<?php

declare(strict_types=1);

namespace App\Exceptions\EnrollmentGoal;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 個人学習目標の達成済 / 未達成の切り替えの例外（HTTP 409）。
 */
class EnrollmentGoalInvalidTransitionException extends ConflictHttpException
{
    public static function alreadyAchieved(): self
    {
        return new self(
            'この目標はすでに達成済みです。',
        );
    }

    public static function notAchieved(): self
    {
        return new self(
            'この目標はすでに未達成です。',
        );
    }

    private function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
