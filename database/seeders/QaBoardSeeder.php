<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * 質問掲示板の開発用データを投入する。
 *
 * - 公開資格ごとに未解決・解決済みの質問を作成
 * - 回答0件・1件・複数件を混在
 * - 固定受講生の投稿を作成
 * - 受講生・担当コーチの回答を混在
 * - 作成日時をずらして新着順を確認可能にする
 */
class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $certifications = Certification::query()
            ->published()
            ->with('coaches')
            ->orderBy('name')
            ->get();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('id')
            ->get();

        $fixedStudent = User::query()
            ->where(
                'email',
                'student@certify-lms.test',
            )
            ->first();

        if ($certifications->isEmpty()) {
            $this->command?->warn(
                'QaBoardSeeder: 公開中の資格が存在しません。',
            );

            return;
        }

        if ($students->isEmpty()) {
            $this->command?->warn(
                'QaBoardSeeder: 受講中の受講生が存在しません。',
            );

            return;
        }

        foreach (
            $certifications as $certification
        ) {
            for (
                $threadIndex = 0; $threadIndex < 5; $threadIndex++
            ) {
                $author = $this->resolveThreadAuthor($students, $fixedStudent, $threadIndex);

                $thread = $this->createThread($certification, $author, $threadIndex);

                $this->createReplies($thread, $certification->coaches, $students, $threadIndex);
            }
        }
    }

    private function resolveThreadAuthor(Collection $students, ?User $fixedStudent, int $threadIndex): User
    {
        if (
            $fixedStudent !== null
            && in_array($threadIndex, [0, 1], true)
        ) {
            return $fixedStudent;
        }

        return $students->random();
    }

    private function createThread(Certification $certification, User $author, int $threadIndex): QaThread
    {
        $titles = [
            '学習を始める順番について教えてください',
            'この分野の理解方法が分かりません',
            '試験対策で優先すべき内容はありますか',
            '演習問題の考え方を確認したいです',
            '復習の進め方について相談です',
        ];

        $bodies = [
            '教材をどの順番で進めると効率的でしょうか。最初から順に読むべきか、問題演習を先に行うべきか迷っています。',
            '教材を読み直しましたが、この分野の考え方をうまく整理できません。理解する際のポイントを教えてください。',
            '試験日まで時間が限られています。特に優先して学習した方がよい内容や分野があれば教えてください。',
            '演習問題を解きましたが、解説を読んでもこの選択肢になる理由を理解できません。考え方を確認したいです。',
            '一度学習した内容を忘れてしまいます。知識を定着させるための復習方法についてアドバイスをお願いします。',
        ];

        $createdAt = now()->subDays($threadIndex);
        $resolved = in_array($threadIndex, [1, 2], true);

        return QaThread::factory()
            ->for($author, 'user')
            ->for($certification)
            ->create([
                'title' => $titles[$threadIndex],
                'body' => $bodies[$threadIndex],
                'status' => $resolved
                    ? QaThreadStatus::Resolved->value
                    : QaThreadStatus::Open->value,
                'resolved_at' => $resolved
                    ? $createdAt->copy()->addDays(1)
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $resolved
                    ? $createdAt->copy()->addDays(1)
                    : $createdAt,
            ]);
    }

    private function createReplies(QaThread $thread, Collection $coaches, Collection $students, int $threadIndex): void
    {
        $bodies = [
            'まずは教材の例題を確認し、考え方を言葉で説明できるか試してみてください。その後に演習問題へ進むと理解しやすくなります。',
            'この分野は全体像を先に把握してから、個別の用語を整理する方法がおすすめです。',
            '間違えた問題だけでなく、正解した問題も理由を説明できるか確認すると知識が定着しやすくなります。',
        ];

        $replyCounts = [0, 1, 2, 3, 1];
        $replyCount = $replyCounts[$threadIndex];

        for ($replyIndex = 0; $replyIndex < $replyCount; $replyIndex++) {
            // コーチが存在し、かつ 50% の確率でコーチを優先
            $isCoach = $coaches->isNotEmpty() && fake()->boolean(50);

            $author = $isCoach
                ? $coaches->random()
                : $students->random();

            $createdAt = $thread->created_at->copy()->addHours($replyIndex + 1);

            QaReply::factory()
                ->for($author, 'user')
                ->for($thread, 'thread')
                ->create([
                    'body' => $bodies[$replyIndex],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
        }
    }
}
