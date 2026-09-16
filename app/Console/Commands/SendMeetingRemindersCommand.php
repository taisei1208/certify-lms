<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingReminderType;
use App\UseCases\MeetingReminder\SendMeetingRemindersAction;
use Illuminate\Console\Command;
use ValueError;

class SendMeetingRemindersCommand extends Command
{
    protected $signature =
        'notifications:send-meeting-reminders
        {--window= : 配信タイミング（eve / one_hour_before）}';

    protected $description =
        '予約済み面談の前日・1時間前リマインダーを配信する';

    public function handle(
        SendMeetingRemindersAction $action,
    ): int {
        $window = $this->option('window');

        if (! is_string($window) || $window === '') {
            $this->error(
                '--windowにはeveまたはone_hour_beforeを指定してください。',
            );

            return self::FAILURE;
        }

        try {
            $reminderType = MeetingReminderType::from(
                $window,
            );
        } catch (ValueError) {
            $this->error(
                "不正なwindowです: {$window}。"
                .'eveまたはone_hour_beforeを指定してください。',
            );

            return self::FAILURE;
        }

        $deliveredCount = $action($reminderType);

        $this->info(
            "{$reminderType->label()}リマインダーを"
            ."{$deliveredCount}件配信しました。",
        );

        return self::SUCCESS;
    }
}
