<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 質問掲示板一覧のN+1非回帰テスト。
 *
 * スレッド数が増えても、投稿者・資格・回答件数の取得によって
 * クエリ数が件数分増加しないことを確認する。
 */
class IndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_thread_count(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->createThreadsWithReplies(
            $student,
            $certification,
            2,
        );

        $baseline = $this->countQueriesFor(
            fn () => $this->actingAs($student)
                ->get(route('qa-board.index')),
        );

        $this->createThreadsWithReplies(
            $student,
            $certification,
            10,
        );

        $scaled = $this->countQueriesFor(
            fn () => $this->actingAs($student)
                ->get(route('qa-board.index')),
        );

        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問掲示板一覧でN+1が発生している可能性があります。基準 {$baseline} → 増加後 {$scaled}",
        );
    }

    private function createThreadsWithReplies(
        User $student,
        Certification $certification,
        int $count,
    ): void {
        QaThread::factory()
            ->count($count)
            ->for($student, 'user')
            ->for($certification)
            ->create()
            ->each(function (QaThread $thread) use ($student): void {
                QaReply::factory()
                    ->count(2)
                    ->for($student, 'user')
                    ->for($thread, 'thread')
                    ->create();
            });
    }

    private function countQueriesFor(
        \Closure $closure,
    ): int {
        $count = 0;

        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
