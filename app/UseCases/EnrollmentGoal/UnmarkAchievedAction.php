<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\EnrollmentGoal\EnrollmentGoalInvalidTransitionException;
use App\Models\EnrollmentGoal;

/**
 * 達成済みの個人学習目標を未達成へ戻す。
 */
final class UnmarkAchievedAction
{
    /**
     * @throws EnrollmentGoalInvalidTransitionException
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at === null) {
            throw EnrollmentGoalInvalidTransitionException::notAchieved();
        }

        $goal->update(['achieved_at' => null]);

        return $goal->refresh();
    }
}
