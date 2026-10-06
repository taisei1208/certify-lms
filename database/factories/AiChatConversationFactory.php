<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatConversation>
 */
class AiChatConversationFactory extends Factory
{
    protected $model = AiChatConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student()->inProgress(),
            'enrollment_id' => null,
            'section_id' => null,
            'title' => fake()->randomElement([
                '学習内容についての相談',
                '試験対策の進め方',
                '苦手分野の確認',
                '教材の内容について',
            ]),
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ];
    }

    /**
     * 指定した受講登録を会話コンテキストにする。
     */
    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn (): array => [
            'user_id' => $enrollment->user_id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    /**
     * 指定したSectionを会話コンテキストにする。
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn (): array => [
            'section_id' => $section->id,
        ]);
    }

    /**
     * AIによるタイトル自動更新を無効にする。
     */
    public function autoTitleDisabled(): static
    {
        return $this->state(fn (): array => [
            'auto_title_enabled' => false,
        ]);
    }
}
