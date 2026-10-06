<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\GeminiApiException;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Http\Client\RequestException;

/**
 * Gemini generateContent APIを扱うService。
 */
class GeminiService
{
    /**
     * 会話履歴とコンテキストを基に回答を生成する。
     */
    public function generate(Collection $messages, ?string $context = null): array
    {
        $apiKey = config('ai-chat.gemini.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw GeminiApiException::missingApiKey();
        }

        $model = (string) config('ai-chat.gemini.model');

        $baseUrl = (string) config('ai-chat.gemini.base_url');

        $timeout = (int) config('ai-chat.gemini.timeout');

        $startedAt = hrtime(true);

        $url = sprintf(
            '%s/models/%s:generateContent',
            $baseUrl,
            $model,
        );

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout(5)
                ->timeout($timeout)
                ->retry(times: 2, sleepMilliseconds: 100, when: function (Throwable $exception,): bool
                {
                    if ($exception instanceof ConnectionException
                    ) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && $exception->response->serverError();
                    },
                    throw: false,
                )
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [
                            [
                                'text' => $this->systemInstruction($context),
                            ],
                        ],
                    ],
                    'contents' => $this->contents($messages),
                ]);
        } catch (ConnectionException $exception) {
            throw new GeminiApiException(
                'Gemini APIへ接続できませんでした。',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new GeminiApiException(
                'Gemini APIとの通信に失敗しました。',
                previous: $exception,
            );
        }

        $responseTimeMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $this->ensureSuccessful($response);

        $content = $this->extractContent($response);

        if ($content === '') {
            throw GeminiApiException::invalidResponse();
        }

        return [
            'content' => $content,
            'model' => (string) ($response->json('modelVersion') ?? $model),
            'input_tokens' => $this->nullableInt($response->json('usageMetadata.promptTokenCount')),
            'output_tokens' => $this->nullableInt($response->json('usageMetadata.candidatesTokenCount')),
            'total_tokens' => $this->nullableInt($response->json('usageMetadata.totalTokenCount')),
            'response_time_ms' => $responseTimeMs,
        ];
    }

    /**
     * 会話内容からタイトルを生成する。
     */
    public function generateTitle(string $question, string $answer): string
    {
        $temporaryMessages = new Collection([
            new AiChatMessage([
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => implode("\n\n", [
                    '次の学習相談に短いタイトルを付けてください。',
                    'タイトルだけを返してください。',
                    '30文字以内にしてください。',
                    "質問: {$question}",
                    "回答: {$answer}",
                ]),
            ]),
        ]);

        $result = $this->generate($temporaryMessages);

        return Str::limit($result['content'], 30, '');
    }

    /**
     * Geminiへ送る会話履歴を構築する。
     */
    private function contents(Collection $messages): array
    {
        $historyLimit = config('ai-chat.history_limit');

        return $messages
            ->filter(
                fn (AiChatMessage $message): bool => $message->status === AiChatMessageStatus::Completed && $message->content !== '',
            )
            ->take(-$historyLimit)
            ->values()
            ->map(
                fn (AiChatMessage $message): array => [
                    'role' => match ($message->role) {
                        AiChatMessageRole::User => 'user',
                        AiChatMessageRole::Assistant => 'model',
                    },
                    'parts' => [
                        [
                            'text' => $message->content,
                        ],
                    ],
                ],
            )
            ->all();
    }

    /**
     * システム指示へ資格・教材コンテキストを追加する。
     */
    private function systemInstruction(
        ?string $context,
    ): ?string {
        $systemPrompt = (string) config(
            'ai-chat.system_prompt',
            'あなたは資格学習を支援するAIアシスタントです。',
        );

        if ($context === null || $context === '') {
            return $systemPrompt;
        }

        return implode("\n\n", [
            $systemPrompt,
            '以下は受講生が現在学習している文脈です。',
            $context,
            'この文脈を踏まえて回答してください。',
        ]);
    }

    /**
     * Gemini APIのエラー応答を業務例外へ変換する。
     */
    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = (string) (
            $response->json('error.message')
            ?? 'Unknown Gemini API error.'
        );

        throw GeminiApiException::requestFailed(
            $response->status(),
            Str::limit($detail, 1000),
        );
    }

    /**
     * candidates内のテキストを取り出す。
     */
    private function extractContent(Response $response): string
    {
        $parts = $response->json('candidates.0.content.parts', []);

        return trim(
            collect($parts)->pluck('text')->filter(
                fn (mixed $text): bool => is_string($text),
            )->implode("\n"),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value)
            ? (int) $value
            : null;
    }
}
