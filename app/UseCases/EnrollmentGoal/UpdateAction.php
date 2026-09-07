<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人学習目標の基本情報を更新する。
 *
 * 達成状態は本処理では変更しない。
 */
final class UpdateAction
{
    /**
     * @param array{
     *     title: string,
     *     description?: string|null,
     *     target_date: string
     * } $data
     */
    public function __invoke(EnrollmentGoal $goal, array $data): EnrollmentGoal
    {
        $goal->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'],
        ]);

        return $goal->refresh();
    }
}
