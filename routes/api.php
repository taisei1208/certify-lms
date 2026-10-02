<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// 受講生・コーチ共有 — 通知ポップオーバー (通知一覧 / 既読化)
// ============================================================
Route::middleware(['auth:sanctum', 'role:student,coach'])
    ->prefix('v1/notifications')
    ->name('api.v1.notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');

        Route::post('/{notification}/read', [NotificationController::class, 'read'])->name('read');

        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
    });
