<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AIチャットメッセージの処理状態を表す。
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '応答中',
            self::Completed => '完了',
            self::Error => 'エラー',
        };
    }
}
