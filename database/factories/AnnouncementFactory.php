<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'body' => fake()->realTextBetween(100, 500),
            'target_type' => AnnouncementTargetType::AllStudents->value,

            'target_certification_id' => null,
            'target_user_id' => null,

            'created_by_user_id' => User::factory()->admin()->inProgress(),

            'dispatched_count' => fake()->numberBetween(1, 20),
            'dispatched_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * 全受講生向けのお知らせ。
     */
    public function allStudents(): static
    {
        return $this->state(fn (): array => [
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
        ]);
    }

    /**
     * 資格指定のお知らせ。
     */
    public function forCertification(Certification $certification): static
    {
        return $this->state(fn (): array => [
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
        ]);
    }

    /**
     * ユーザー指定のお知らせ。
     */
    public function forUser(User $student): static
    {
        return $this->state(fn (): array => [
            'target_type' => AnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $student->id,
        ]);
    }

    /**
     * 配信処理が完了する前の状態。
     */
    public function undispatched(): static
    {
        return $this->state(fn (): array => [
            'dispatched_count' => 0,
            'dispatched_at' => null,
        ]);
    }
}
