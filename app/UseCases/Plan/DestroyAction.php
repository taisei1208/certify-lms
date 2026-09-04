<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;

/**
 * 参照されていない下書きプランを物理削除する。
 */
final class DestroyAction
{
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanInvalidTransitionException::forDeleteStatus();
        }

        if ($plan->users()->exists()) {
            throw PlanInvalidTransitionException::forDeleteUsers();
        }

        if ($plan->userPlanLogs()->exists()) {
            throw PlanInvalidTransitionException::forDeleteHistory();
        }

        $plan->delete();
    }
}
