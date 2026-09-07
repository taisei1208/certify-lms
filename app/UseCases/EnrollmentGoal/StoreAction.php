<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;

/**
 * 受講登録配下に個人学習目標を作成する。
 */
final class StoreAction
{
    /**
     * @param array{
     *     title: string,
     *     description?: string|null,
     *     target_date: string
     * } $data
     */
    public function __invoke(Enrollment $enrollment, array $data): EnrollmentGoal
    {
        return $enrollment->goals()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'],
            'achieved_at' => null,
        ]);
    }
}
