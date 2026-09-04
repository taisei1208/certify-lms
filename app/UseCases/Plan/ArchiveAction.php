<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;

/**
 * 公開中のプランをアーカイブする。
 */
final class ArchiveAction
{
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Published) {
            throw PlanInvalidTransitionException::forArchive();
        }

        $plan->update([
            'status' => PlanStatus::Archived,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
