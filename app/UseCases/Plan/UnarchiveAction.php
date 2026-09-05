<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;

/**
 * アーカイブされたプランを下書きに戻す。
 */
final class UnarchiveAction
{
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Archived) {
            throw PlanInvalidTransitionException::forUnarchive();
        }

        $plan->update([
            'status' => PlanStatus::Draft,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
