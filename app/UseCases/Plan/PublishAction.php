<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;

/**
 * 下書きのプランを公開する。
 */
final class PublishAction
{
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanInvalidTransitionException::forPublish();
        }

        $plan->update([
            'status' => PlanStatus::Published,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
