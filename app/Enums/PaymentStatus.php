<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 面談パック購入の決済状態を表す Enum。
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '処理中',
            self::Succeeded => '支払済み',
            self::Failed => '失敗',
            self::Refunded => '返金済み',
        };
    }
}
