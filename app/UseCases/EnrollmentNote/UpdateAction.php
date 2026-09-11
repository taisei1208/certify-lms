<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

/**
 * 受講生メモを更新する。
 */
final class UpdateAction
{
    public function __invoke(EnrollmentNote $note, array $validated): EnrollmentNote
    {
        $note->update([
            'body' => $validated['body']
        ]);

        return $note->refresh();
    }
}