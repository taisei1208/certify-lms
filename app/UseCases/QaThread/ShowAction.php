<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問スレッド詳細を、投稿者・資格・回答一覧付きで取得する。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread->load(['user', 'certification', 'replies' => fn ($query) => $query->with('user')->oldest()])
            ->loadCount('replies');
    }
}
