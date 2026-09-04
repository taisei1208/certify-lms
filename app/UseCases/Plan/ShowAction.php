<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * プラン詳細画面で必要な関連情報を取得するユースケース。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load(['users', 'createdBy', 'updatedBy']);
    }
}
