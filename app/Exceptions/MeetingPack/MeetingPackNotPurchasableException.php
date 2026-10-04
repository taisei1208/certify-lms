<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use DomainException;

class MeetingPackNotPurchasableException extends DomainException
{
    public static function make(): self
    {
        return new self(
            '指定された面談パックは現在購入できません。',
        );
    }
}
