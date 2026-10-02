<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\IndexRequest;
use App\Http\Resources\NotificationResource;
use App\UseCases\Notification\FetchPopoverAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 通知ポップオーバー用APIを提供する。
 */
class NotificationController extends Controller
{
    public function index(IndexRequest $request, FetchPopoverAction $action): AnonymousResourceCollection
    {
        $tab = ($request->validated('tab') ?? '') ?: 'all';

        $result = $action($request->user(), $tab);

        return NotificationResource::collection($result['notifications'])->additional([
            'meta' => [
                'tab' => $tab,
                'unread_count' => $result['unread_count'],
            ],
        ]);
    }

    public function read(Request $request, string $notification, MarkAsReadAction $action): JsonResponse
    {
        $url = $action($request->user(), $notification);

        return response()->json([
            'message' => '通知を既読にしました。',
            'url' => $url,
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    public function readAll(Request $request, MarkAllAsReadAction $action
    ): JsonResponse {
        $action($request->user());

        return response()->json([
            'message' => 'すべての通知を既読にしました。',
            'unread_count' => 0,
        ]);
    }
}
