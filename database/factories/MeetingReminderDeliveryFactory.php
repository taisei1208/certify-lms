<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingReminderType;
use App\Models\Meeting;
use App\Models\MeetingReminderDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingReminderDelivery>
 */
class MeetingReminderDeliveryFactory extends Factory
{
    protected $model = MeetingReminderDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory()->reserved(),
            'user_id' => User::factory()->student()->inProgress(),
            'reminder_type' => fake()->randomElement(
                MeetingReminderType::cases(),
            )->value,
            'delivered_at' => now(),
        ];
    }

    public function eve(): static
    {
        return $this->state(fn () => [
            'reminder_type' => MeetingReminderType::Eve->value,
        ]);
    }

    public function oneHourBefore(): static
    {
        return $this->state(fn () => [
            'reminder_type' => MeetingReminderType::OneHourBefore->value,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'delivered_at' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'delivered_at' => now(),
        ]);
    }

    public function forMeetingAndUser(Meeting $meeting, User $user): static
    {
        return $this->state(fn () => [
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
        ]);
    }
}
