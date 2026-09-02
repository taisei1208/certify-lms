<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 質問掲示板のスレッドの状態を表すEnum。未解決 / 解決済  の 2 値。
 */
enum QaThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => '未解決',
            self::Resolved => '解決済',
        };
    }
}
