<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 質問掲示板の回答管理 Controller
 */
class QaReplyController extends Controller
{
    public function store(StoreRequest $request, QaThread $thread, StoreAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $action($request->user(), $thread, $validated['body']);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply,
    ): View {
        $this->ensureReplyBelongsToThread($thread, $reply);

        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(UpdateRequest $request, QaThread $thread, QaReply $reply, UpdateAction $action): RedirectResponse
    {
        $this->authorize('update', $reply);

        $validated = $request->validated();

        $action($reply, $validated['body']);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->ensureReplyBelongsToThread($thread, $reply);

        $this->authorize('delete', $reply);

        $action($reply);

        $showRoute = $request->routeIs('admin.*')
            ? 'admin.qa-board.show'
            : 'qa-board.show';

        return redirect()
            ->route($showRoute, $thread)
            ->with('success', '回答を削除しました。');
    }

    private function ensureReplyBelongsToThread(QaThread $thread, QaReply $reply): void
    {
        abort_unless(
            $reply->qa_thread_id === $thread->id,
            404,
        );
    }
}
