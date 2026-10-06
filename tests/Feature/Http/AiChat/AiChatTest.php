<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.daily_limit' => 50,
            'ai-chat.auto_title_enabled' => false,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-2.5-flash',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai-chat.gemini.timeout' => 30,
        ]);
    }

    private function student(): User
    {
        return User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);
    }

    public function test_student_can_manage_own_conversation(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('ai-chat.conversations.store'),
                ['source' => 'full-screen'])
            ->assertRedirect();

        $conversation = AiChatConversation::query()
            ->where('user_id', $student->id)->firstOrFail();

        $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertOk();

        $this->actingAs($student)
            ->patch(
                route('ai-chat.conversations.update', $conversation),
                ['title' => '手動で変更したタイトル']
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'ai_chat_conversations',
            [
                'id' => $conversation->id,
                'title' => '手動で変更したタイトル',
                'auto_title_enabled' => false,
            ]
        );

        $this->actingAs($student)
            ->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertRedirect(route('ai-chat.index'));

        $this->assertDatabaseMissing(
            'ai_chat_conversations', ['id' => $conversation->id]
        );
    }

    public function test_other_student_cannot_view_conversation(): void
    {
        $owner = $this->student();
        $other = $this->student();

        $conversation = AiChatConversation::factory()->for($owner, 'user')->create();

        $this->actingAs($other)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertForbidden();
    }

    public function test_coach_cannot_access_ai_chat(): void
    {
        $coach = User::factory()->create([
            'role' => UserRole::Coach->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $this->actingAs($coach)->get(route('ai-chat.index'))->assertForbidden();
    }

    public function test_student_can_send_message_and_receive_ai_answer(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'AIからの回答です。',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $student = $this->student();

        $conversation = AiChatConversation::factory()->for($student, 'user')->create(['auto_title_enabled' => false]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問内容です。']
            )
            ->assertOk()
            ->assertJsonPath(
                'assistant_message.content',
                'AIからの回答です。',
            );

        $this->assertDatabaseHas(
            'ai_chat_messages',
            [
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User->value,
                'status' => AiChatMessageStatus::Completed->value,
                'content' => '質問内容です。',
            ],
        );

        $this->assertDatabaseHas(
            'ai_chat_messages',
            [
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant->value,
                'status' => AiChatMessageStatus::Completed->value,
                'content' => 'AIからの回答です。',
            ]
        );
    }

    public function test_question_remains_when_gemini_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'Service unavailable',
                ],
            ], 503),
        ]);

        $student = $this->student();

        $conversation = AiChatConversation::factory()->for($student, 'user')->create(['auto_title_enabled' => false]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '失敗時にも残す質問です。']
            )
            ->assertStatus(502);

        $this->assertDatabaseHas(
            'ai_chat_messages',
            [
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User->value,
                'status' => AiChatMessageStatus::Completed->value,
                'content' => '失敗時にも残す質問です。',
            ]
        );

        $this->assertDatabaseHas(
            'ai_chat_messages',
            [
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant->value,
                'status' => AiChatMessageStatus::Error->value,
                'content' => '',
            ]
        );
    }

    public function test_daily_limit_is_enforced(): void
    {
        config(['ai-chat.daily_limit' => 1]);

        $student = $this->student();

        $conversation = AiChatConversation::factory()->for($student, 'user')->create();

        $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => '今日送信済みの質問',
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '上限超過の質問']
            )
            ->assertStatus(429);

        Http::assertNothingSent();
    }
}
