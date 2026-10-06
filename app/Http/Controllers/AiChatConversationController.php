<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\GeminiApiException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\DestroyAction;
use App\UseCases\AiChat\IndexAction;
use App\UseCases\AiChat\ShowAction;
use App\UseCases\AiChat\StoreConversationAction;
use App\UseCases\AiChat\StoreMessageAction;
use App\UseCases\AiChat\UpdateAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AiChatConversationController extends Controller
{
    /**
     * AIチャットの入口。
     *
     * 会話があれば最新の会話へ移動し、
     * なければ空状態を表示する。
     */
    public function index(IndexAction $action): View|RedirectResponse
    {
        $this->authorize('viewAny', AiChatConversation::class);

        $conversation = $action(request()->user());

        if ($conversation !== null) {
            return redirect()->route('ai-chat.conversations.show', $conversation);
        }

        return view('ai-chat.empty-state');
    }

    /**
     * 新しい会話を作成する。
     */
    public function store(
        StoreConversationRequest $request,
        StoreConversationAction $action,
        StoreMessageAction $storeMessage,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();

        $conversation = $action($request->user(), $validated);

        $created = $conversation->wasRecentlyCreated;

        $message = trim((string) ($validated['message'] ?? ''));

        if ($message !== '') {
            try {
                $storeMessage($conversation, $message);
            } catch (GeminiApiException $exception) {
                report($exception);

                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', 'AIが応答できませんでした。しばらく時間をおいて再試行してください。');
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'section_id' => $conversation->section_id,
                    'last_message_at' => $conversation->last_message_at?->toISOString(),
                ],
            ], $created ? 201 : 200);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    /**
     * 会話詳細を表示する。
     */
    public function show(AiChatConversation $conversation, ShowAction $action): View
    {
        $this->authorize('view', $conversation);

        return view('ai-chat.show', [
            'conversation' => $action($conversation),
        ]);
    }

    /**
     * 会話タイトルを手動更新する。
     */
    public function update(
        UpdateConversationRequest $request,
        AiChatConversation $conversation,
        UpdateAction $action,
    ): RedirectResponse {
        $action($conversation, $request->validated('title'));

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    /**
     * 会話を削除する。
     */
    public function destroy(
        AiChatConversation $conversation,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $conversation);

        $action($conversation);

        return redirect()->route('ai-chat.index')->with('success', '会話を削除しました。');
    }
}
