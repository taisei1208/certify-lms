<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\UseCases\Announcement\CreateAction;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcement.management.index', [
            'announcements' => $action(),
        ]);
    }

    public function create(CreateAction $action): View
    {
        $this->authorize('create', Announcement::class);

        $result = $action();

        return view('announcement.management.create', [
            'certifications' => $result['certifications'],
            'students' => $result['students'],
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action
    ): RedirectResponse {
        $announcement = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success',
                "{$announcement->dispatched_count}名にお知らせを配信しました。",
            );
    }

    public function show(Announcement $announcement, ShowAction $action): View
    {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', [
            'announcement' => $action($announcement),
        ]);
    }
}
