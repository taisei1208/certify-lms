<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarConnection>
 */
class GoogleCalendarConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => 'seed-access-token-'.$this->faker->uuid(),
            'refresh_token' => 'seed-refresh-token-'.$this->faker->uuid(),
            'token_expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now()->subDays(
                $this->faker->numberBetween(1, 30),
            ),
        ];
    }

    /**
     * アクセストークンの有効期限が切れた状態。
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'token_expires_at' => now()->subHour(),
        ]);
    }

    /**
     * リフレッシュトークンを持たない状態。
     */
    public function withoutRefreshToken(): static
    {
        return $this->state(fn (): array => [
            'refresh_token' => null,
        ]);
    }
}
