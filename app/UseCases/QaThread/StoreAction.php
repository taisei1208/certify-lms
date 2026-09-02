<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;

/**
 * 受講生による質問スレッドの新規投稿。
 */
final class StoreAction
{
    /**
     * @param array{
     *     certification_id: string,
     *     title: string,
     *     body: string
     * } $validated
     */
    public function __invoke(User $student, array $validated): QaThread
    {
        return QaThread::query()->create([
            'user_id' => $student->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => QaThreadStatus::Open,
            'resolved_at' => null,
        ]);
    }
}
