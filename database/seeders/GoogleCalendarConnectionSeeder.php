<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Seeder;

class GoogleCalendarConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connectedCoach = User::query()
            ->where('email', 'coach2@certify-lms.test')
            ->first();

        if ($connectedCoach === null) {
            $this->command?->warn(
                'GoogleCalendarConnectionSeeder: コーチが見つかりません。',
            );

            return;
        }

        GoogleCalendarConnection::query()->updateOrCreate(
            [
                'user_id' => $connectedCoach->id,
            ],
            [
                'access_token' => 'seed-dummy-access-token',
                'refresh_token' => 'seed-dummy-refresh-token',
                'token_expires_at' => now()->addHour(),
                'calendar_id' => 'primary',
                'connected_at' => now()->subDays(7),
            ],
        );

        $this->command?->info(
            'コーチ花子をGoogle Calendar連携済みとして投入しました。',
        );
    }
}
