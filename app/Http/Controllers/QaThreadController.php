<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 質問掲示板の質問管理 Controller
 */
class QaThreadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $this->authorize('viewAny', QaThread::class);

        $validated = $request->validated();

        $result = $action($request->user(), $validated);

        return view('qa-thread.index', [
            'threads' => $result['threads'],
            'certifications' => $result['certifications'],
            'filters' => $validated,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::query()
                ->published()
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $this->authorize('create', QaThread::class);

        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread->load('certification'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request, QaThread $thread, UpdateAction $action): RedirectResponse
    {
        $this->authorize('update', $thread);

        $thread = $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action($thread);

        $route = $request->routeIs('admin.qa-board.*')
            ? 'admin.qa-board.index'
            : 'qa-board.index';

        return redirect()
            ->route($route)
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}
