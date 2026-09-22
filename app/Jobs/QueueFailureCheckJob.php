<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QueueFailureCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function backoff(): array
    {
        // 動作確認用に短くする
        return [5, 10];
    }

    public function handle(): void
    {
        Log::info('キュー失敗確認', [
            'attempt' => $this->attempts(),
        ]);

        throw new RuntimeException('失敗ジョブ記録の動作確認');
    }
}
