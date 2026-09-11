<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 受講生メモのシーダー
 *
 * - 複数の受講生・複数の資格・複数のコーチにまたがってメモ
 * - 自分が作成したメモと他コーチが作成したメモを混在させる（編集 / 削除の出し分け・管理者の越境操作を確認）
 * - コーチの担当資格と担当外資格の両方の受講登録があり、受講生本人の受講登録にもメモがある（担当外での閲覧拒否・受講生にはメモが見えない分離を確認）
 */
class EnrollmentNoteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('email', 'admin@certify-lms.test')
            ->first();

        $coach1 = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        $coach2 = User::query()
            ->where('email', 'coach2@certify-lms.test')
            ->first();

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($admin === null || $coach1 === null || $coach2 === null || $fixedStudent === null) {
            $this->command?->warn('EnrollmentNoteSeeder: 固定ユーザーが不足しています。');

            return;
        }

        $this->seedSharedCertification($admin, $coach1, $coach2, $fixedStudent);

        $this->seedCoachOnlyCertifications($coach1, $coach2, $fixedStudent);

        $this->seedOtherStudent($coach1, $fixedStudent);
    }

    /**
     * 2人のコーチが担当している資格に、
     * コーチ1・コーチ2・管理者のメモを混在させる。
     */
    private function seedSharedCertification(User $admin, User $coach1, User $coach2, User $fixedStudent): void
    {
        $enrollment = Enrollment::query()
            ->where('user_id', $fixedStudent->id)
            ->whereHas('certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach1->id
                )
            )
            ->whereHas('certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach2->id
                )
            )
            ->first();

        if ($enrollment === null) {
            $this->command?->warn('EnrollmentNoteSeeder: 複数コーチが担当する受講登録がありません。');

            return;
        }

        $this->createNote(
            $enrollment,
            $coach1,
            '最近は学習時間を安定して確保できています。次回面談では苦手分野の進め方を確認します。',
            5,
        );

        $this->createNote(
            $enrollment,
            $coach2,
            'Q&Aで質問していた内容について、教材の該当箇所を案内しました。理解できたか次回確認します。',
            3,
        );

        $this->createNote(
            $enrollment,
            $admin,
            '運営からの連絡事項を確認済みです。次回の面談時に受講生へ案内してください。',
            1,
        );
    }

    /**
     * 片方のコーチだけが担当する資格にもメモを作成する。
     *
     * 担当コーチからは閲覧可能、
     * もう一方の担当外コーチからは閲覧不可になる。
     */
    private function seedCoachOnlyCertifications(User $coach1, User $coach2, User $fixedStudent): void
    {
        $coach1Enrollment = Enrollment::query()
            ->where(
                'user_id', $fixedStudent->id
            )
            ->whereHas(
                'certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach1->id
                )
            )
            ->whereDoesntHave(
                'certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach2->id
                )
            )
            ->first();

        if ($coach1Enrollment !== null) {
            $this->createNote(
                $coach1Enrollment,
                $coach1,
                '演習問題の正答率が上がっています。次回は模試へ進むタイミングを相談します。',
                4,
            );
        }

        $coach2Enrollment = Enrollment::query()
            ->where(
                'user_id', $fixedStudent->id
            )
            ->whereHas(
                'certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach2->id
                )
            )
            ->whereDoesntHave(
                'certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach1->id
                )
            )
            ->first();

        if ($coach2Enrollment !== null) {
            $this->createNote(
                $coach2Enrollment,
                $coach2,
                '学習計画に遅れが見られるため、次回面談で目標期日を見直す予定です。',
                2,
            );
        }
    }

    /**
     * 固定受講生以外にもメモを作成し、
     * 複数受講生にまたがる状態にする。
     */
    private function seedOtherStudent(User $coach, User $fixedStudent
    ): void {
        $enrollment = Enrollment::query()
            ->where(
                'user_id', '!=', $fixedStudent->id,
            )
            ->whereHas(
                'certification.coaches',
                fn ($query) => $query->where(
                    'users.id', $coach->id
                )
            )
            ->first();

        if ($enrollment === null) {
            $this->command?->warn(
                'EnrollmentNoteSeeder: デモ受講生の担当受講登録がありません。',
            );

            return;
        }

        $this->createNote(
            $enrollment,
            $coach,
            'チャットへの返信が遅れているため、学習を継続できているか確認します。',
            2,
        );
    }

    private function createNote(Enrollment $enrollment, User $author, string $body, int $daysAgo): void
    {
        $createdAt = now()
            ->subDays($daysAgo)
            ->setTime(10, 0);

        EnrollmentNote::query()->firstOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'author_user_id' => $author->id,
                'body' => $body,
            ],
            [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        );
    }
}
