<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $tab = $request->validated('tab') ?? 'all';

        $result = $action($request->user(), $tab);

        return view('notifications.index', [
            'notifications' => $result['notifications'],
            'unreadCount' => $result['unread_count'],
            'tab' => $tab,
        ]);
    }

    public function markAsRead(Request $request, string $notification, MarkAsReadAction $action): RedirectResponse
    {
        $url = $action($request->user(), $notification);

        return redirect()->to($url);
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
