<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\GeminiApiException;
use App\Models\AiChatMessage;
use App\Services\GeminiService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.history_limit' => 20,
            'ai-chat.system_prompt' => '資格学習を支援してください。',
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-3.8-flash',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai-chat.gemini.timeout' => 30,
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @return Collection<int, AiChatMessage>
     */
    private function messages(): Collection
    {
        return new Collection([
            new AiChatMessage([
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => '質問です。',
            ]),
        ]);
    }

    public function test_generate_returns_gemini_answer(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => '生成された回答です。',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $messages = new Collection([
            new AiChatMessage([
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => '質問です。',
            ]),
            new AiChatMessage([
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => '過去の回答です。',
            ]),
        ]);

        $result = app(GeminiService::class)
            ->generate($messages, '対象資格: サンプル資格');

        $this->assertSame('生成された回答です。', $result['content']);

        Http::assertSent(
            function ($request): bool {
                $data = $request->data();

                return
                    $request->hasHeader('x-goog-api-key', 'test-api-key')
                    && data_get($data, 'contents.0.role') === 'user'
                    && data_get($data, 'contents.1.role') === 'model'
                    && str_contains((string) data_get($data, 'system_instruction.parts.0.text'), 'サンプル資格');
            }
        );
    }

    public function test_generate_throws_exception_when_api_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'Service unavailable',
                ],
            ], 503),
        ]);

        $this->expectException(GeminiApiException::class);

        app(GeminiService::class)->generate($this->messages());
    }

    public function test_generate_throws_exception_when_api_key_is_missing(): void
    {
        config(['ai-chat.gemini.api_key' => null]);

        Http::fake();

        $this->expectException(GeminiApiException::class);

        app(GeminiService::class)->generate($this->messages());

        Http::assertNothingSent();
    }

    public function test_generate_throws_exception_when_response_is_empty(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [],
                        ],
                    ],
                ],
            ], 200),
        ]);

        try {
            app(GeminiService::class)->generate($this->messages());

            $this->fail(
                '空応答時にGeminiApiExceptionが発生しませんでした。'
            );
        } catch (GeminiApiException $exception) {
            $this->assertNotSame(
                '',
                $exception->getMessage(),
            );
        }

        Http::assertSentCount(1);
    }

    public function test_generate_retries_temporary_error_and_succeeds(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()->push([
                'error' => [
                    'message' => 'Service unavailable.',
                ],
            ], 503)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => '再試行後の回答です。',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $result = app(GeminiService::class)->generate($this->messages());

        $this->assertSame('再試行後の回答です。', $result['content']);

        Http::assertSentCount(2);
    }
}
