<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問スレッドへ回答を投稿する。
 */
final class StoreAction
{
    public function __invoke(User $author, QaThread $thread, string $body): QaReply
    {
        return $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }
}
