<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inputTokens = fake()->numberBetween(20, 200);
        $outputTokens = fake()->numberBetween(50, 500);

        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => fake()->realText(200),
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'response_time_ms' => fake()->numberBetween(300, 5000),
        ];
    }

    /**
     * 受講生が送信したメッセージ。
     */
    public function asUser(): static
    {
        return $this->state(fn (): array => [
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => fake()->realText(100),
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
            'response_time_ms' => null,
        ]);
    }

    /**
     * AIが正常に回答したメッセージ。
     */
    public function asAssistant(): static
    {
        return $this->state(fn (): array => [
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
        ]);
    }

    /**
     * Gemini APIの応答待ちメッセージ。
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Pending->value,
            'content' => '',
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
            'response_time_ms' => null,
        ]);
    }

    /**
     * Gemini API呼び出しに失敗したメッセージ。
     */
    public function error(): static
    {
        return $this->state(fn (): array => [
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
            'content' => '',
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
            'response_time_ms' => null,
        ]);
    }
}
