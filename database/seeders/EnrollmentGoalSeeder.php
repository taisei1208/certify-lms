<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 個人学習目標の開発用データを投入する。
 *
 * - 固定受講生に達成済み・未達成の目標を混在させる
 * - 複数の受講生・資格に目標を散布する
 * - 目標期日は確定仕様に合わせて今日以降にする
 */
class EnrollmentGoalSeeder extends Seeder
{
    public function run(): void
    {
        if (! Enrollment::query()->exists()) {
            $this->command?->warn(
                'EnrollmentGoalSeeder: 受講登録が存在しません。先にEnrollmentSeederを実行してください。',
            );

            return;
        }

        $this->seedFixedStudent();
        $this->seedDemoStudents();
    }

    /**
     * 固定受講生の受講登録に、達成済み・未達成の目標を作成する。
     */
    private function seedFixedStudent(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'EnrollmentGoalSeeder: 固定受講生が存在しません。',
            );

            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->orderBy('created_at')
            ->first();

        if ($enrollment === null) {
            $this->command?->warn(
                'EnrollmentGoalSeeder: 固定受講生の受講登録が存在しません。',
            );

            return;
        }

        $goals = [
            [
                'title' => '教材の基礎範囲を読み終える',
                'description' => 'Part 1から順番に教材を読み、各Sectionの内容を理解する。',
                'target_date' => today()->addDays(14)->toDateString(),
                'achieved_at' => null,
            ],
            [
                'title' => '演習問題を50問解く',
                'description' => '間違えた問題は解説を読み、翌日にもう一度解き直す。',
                'target_date' => today()->addDays(30)->toDateString(),
                'achieved_at' => null,
            ],
            [
                'title' => '学習計画を作成する',
                'description' => '試験日から逆算し、週ごとの学習内容を決める。',
                'target_date' => today()->addDays(7)->toDateString(),
                'achieved_at' => now()->subDay(),
            ],
            [
                'title' => '模擬試験を1回受験する',
                'description' => '現在の理解度を確認し、苦手分野を整理する。',
                'target_date' => today()->addDays(21)->toDateString(),
                'achieved_at' => now()->subDays(2),
            ],
        ];

        foreach ($goals as $goal) {
            EnrollmentGoal::query()->updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'title' => $goal['title'],
                    'description' => $goal['description'],
                    'target_date' => $goal['target_date'],
                    'achieved_at' => $goal['achieved_at'],
                ]
            );
        }
    }

    private function seedDemoStudents(): void
    {
        $fixedStudentId = User::query()
            ->where('email', 'student@certify-lms.test')
            ->value('id');

        $enrollments = Enrollment::query()
            ->whereHas('user', function ($query): void {
                $query->where('role', UserRole::Student->value);
            })
            ->when(
                $fixedStudentId !== null,
                fn ($query) => $query->where('user_id', '!=', $fixedStudentId)
            )
            ->orderBy('created_at')
            ->get();

        if ($enrollments->isEmpty()) {
            $this->command?->warn(
                'EnrollmentGoalSeeder: デモ受講生の受講登録が存在しません。',
            );

            return;
        }

        $goalPatterns = [
            [
                'title' => '教材を毎日1Section進める',
                'description' => '無理のない範囲で毎日継続する。',
                'days' => 14,
            ],
            [
                'title' => '苦手分野を復習する',
                'description' => '間違えた問題を中心に復習する。',
                'days' => 21,
            ],
            [
                'title' => '模擬試験で80点を取る',
                'description' => '模擬試験を受験し、合格点を目指す。',
                'days' => 30,
            ],
            [
                'title' => '学習時間を週10時間確保する',
                'description' => '平日と休日に学習時間を分けて確保する。',
                'days' => 7,
            ],
        ];

        foreach ($enrollments as $index => $enrollment) {
            $pattern = $goalPatterns[$index % count($goalPatterns)];

            /*
             * 偶数番目は未達成、奇数番目は達成済みにして
             * 表示と認可確認に使える状態にする。
             */
            $achievedAt = $index % 2 === 0
                ? null
                : now()->subDays(($index % 5) + 1);

            EnrollmentGoal::query()->updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'title' => $pattern['title'],
                ],
                [
                    'description' => $pattern['description'],
                    'target_date' => today()
                        ->addDays($pattern['days'] + $index)
                        ->toDateString(),
                    'achieved_at' => $achievedAt,
                ],
            );
        }
    }
}
