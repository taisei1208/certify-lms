<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\GeminiApiException;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\StoreMessageAction;
use Illuminate\Http\JsonResponse;

class AiChatMessageController extends Controller
{
    /**
     * 質問を保存し、Geminiの回答を取得する。
     */
    public function store(
        StoreMessageRequest $request,
        AiChatConversation $conversation,
        StoreMessageAction $action,
    ): JsonResponse {
        $this->authorize('sendMessage', $conversation);

        try {
            $result = $action($conversation, $request->validated('content'));
        } catch (GeminiApiException $exception) {
            report($exception);

            return response()->json([
                'message' => 'AIが応答できませんでした。しばらく時間をおいて再試行してください。',
                'upstream_status' => $exception->upstreamStatus,
            ], 502);
        }

        return response()->json([
            'user_message' => $this->messageData($result['user_message']),
            'assistant_message' => $this->messageData($result['assistant_message']),
            'conversation' => [
                'id' => $result['conversation']->id,
                'title' => $result['conversation']->title,
                'section_id' => $result['conversation']->section_id,
                'last_message_at' => $result['conversation']->last_message_at?->toISOString(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageData($message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'status' => $message->status->value,
            'content' => $message->content,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
