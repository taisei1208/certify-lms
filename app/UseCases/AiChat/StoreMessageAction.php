<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\GeminiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class StoreMessageAction
{
    public function __construct(
        private readonly GeminiService $gemini,
    ) {}

    /**
     * @return array{
     *     user_message: AiChatMessage,
     *     assistant_message: AiChatMessage,
     *     conversation: AiChatConversation
     * }
     */
    public function __invoke(AiChatConversation $conversation, string $content): array
    {
        $this->ensureWithinDailyLimit($conversation);

        /*
         * Geminiを呼ぶ前に質問と応答待ち行を保存する。
         * APIが失敗しても質問を履歴に残すため。
         */
        [
            $userMessage,
            $assistantMessage,
        ] = DB::transaction(
            function () use ($conversation, $content): array {
                $userMessage = $conversation->messages()->create([
                    'role' => AiChatMessageRole::User,
                    'status' => AiChatMessageStatus::Completed,
                    'content' => $content,
                ]);

                $assistantMessage = $conversation->messages()->create([
                    'role' => AiChatMessageRole::Assistant,
                    'status' => AiChatMessageStatus::Pending,
                    'content' => '',
                ]);

                $conversation->update([
                    'last_message_at' => now(),
                ]);

                return [
                    $userMessage,
                    $assistantMessage,
                ];
            },
        );

        try {
            $messages = $conversation->messages()->oldest('created_at')->oldest('id')->get();

            $result = $this->gemini->generate(
                $messages, $this->buildContext($conversation)
            );

            $answer = $result['content'];

            $assistantMessage->update([
                'status' => AiChatMessageStatus::Completed,
                'content' => $answer,
            ]);

            $conversation->update(['last_message_at' => now()]);

            $this->updateAutomaticTitle($conversation, $content, $answer);
        } catch (Throwable $exception) {
            $assistantMessage->update([
                'status' => AiChatMessageStatus::Error,
                'content' => '',
            ]);

            $conversation->update(['last_message_at' => now()]);

            throw $exception;
        }

        return [
            'user_message' => $userMessage->refresh(),
            'assistant_message' => $assistantMessage->refresh(),
            'conversation' => $conversation->refresh(),
        ];
    }

    private function ensureWithinDailyLimit(AiChatConversation $conversation): void
    {
        $dailyLimit = config('ai-chat.daily_limit');

        $usedToday = AiChatMessage::query()
            ->where('role', AiChatMessageRole::User->value)
            ->whereDate('created_at', today())
            ->whereHas('conversation',
                fn ($query) => $query->where('user_id', $conversation->user_id),
            )
            ->count();

        if ($usedToday >= $dailyLimit) {
            throw new AiChatDailyLimitExceededException;
        }
    }

    private function buildContext(AiChatConversation $conversation): ?string
    {
        $conversation->loadMissing([
            'enrollment.certification',
            'section.chapter.part.certification',
        ]);

        $context = [];

        $certificationName = $conversation->enrollment?->certification?->name ?? $conversation->section?->chapter?->part?->certification?->name;

        if ($certificationName !== null) {
            $context[] = "対象資格: {$certificationName}";
        }

        if ($conversation->section !== null) {
            $context[] = sprintf(
                "閲覧中の教材Section: %s\n教材本文:\n%s",
                $conversation->section->title,
                Str::limit(
                    (string) $conversation->section->body,
                    10000,
                    '',
                ),
            );
        }

        return $context === []
            ? null
            : implode("\n\n", $context);
    }

    private function updateAutomaticTitle(AiChatConversation $conversation, string $question, string $answer): void
    {
        if (
            ! (bool) config('ai-chat.auto_title_enabled')
            || ! $conversation->auto_title_enabled
        ) {
            return;
        }

        $userMessageCount = $conversation->messages()->where('role', AiChatMessageRole::User->value)->count();

        if ($userMessageCount !== 1) {
            return;
        }

        try {
            $title = $this->gemini->generateTitle($question, $answer);

            if ($title !== '') {
                $conversation->update(['title' => $title]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
