<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingReminderType: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';

    public function label(): string
    {
        return match ($this) {
            self::Eve => '前日',
            self::OneHourBefore => '1時間前',
        };
    }
}
