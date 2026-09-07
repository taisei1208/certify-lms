<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\EnrollmentGoal\EnrollmentGoalInvalidTransitionException;
use App\Models\EnrollmentGoal;

/**
 * 未達成の個人学習目標を達成済みにする。
 */
final class MarkAchievedAction
{
    /**
     * @throws EnrollmentGoalInvalidTransitionException
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at !== null) {
            throw EnrollmentGoalInvalidTransitionException::alreadyAchieved();
        }

        $goal->update(['achieved_at' => now()]);

        return $goal->refresh();
    }
}
