<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;

/**
 * プランを下書き状態で新規作成するユースケース。
 */
final class StoreAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     duration_days: int,
     *     default_meeting_quota: int,
     *     sort_order?: int|null
     * } $data
     */
    public function __invoke(User $admin, array $data): Plan
    {
        return Plan::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_days' => $data['duration_days'],
            'default_meeting_quota' => $data['default_meeting_quota'],
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => PlanStatus::Draft,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }
}
