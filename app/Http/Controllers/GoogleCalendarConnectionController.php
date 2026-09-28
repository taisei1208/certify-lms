<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\GoogleCalendarConnection\CallbackAction;
use App\UseCases\GoogleCalendarConnection\ConnectAction;
use App\UseCases\GoogleCalendarConnection\DestroyAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleCalendarConnectionController extends Controller
{
    private const STATE_SESSION_KEY =
        'google_calendar_oauth_state';

    /**
     * Google OAuth認可画面へリダイレクトする。
     */
    public function connect(Request $request, ConnectAction $action): RedirectResponse
    {
        $state = Str::random(64);

        $request->session()->put(
            self::STATE_SESSION_KEY, $state
        );

        return redirect()->away($action($state));
    }

    /**
     * GoogleからのOAuthコールバックを処理する。
     */
    public function callback(Request $request, CallbackAction $action): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);

        $receivedState = $request->query('state');

        if (! is_string($expectedState)
        || ! is_string($receivedState) || ! hash_equals($expectedState, $receivedState)) {
            abort(403, 'OAuth認証のstateが一致しません。');
        }

        if ($request->filled('error')) {
            return $this->redirectToMeetingSettings()->with('error', 'Google Calendarとの連携がキャンセルされました。');
        }

        $authorizationCode = $request->query('code');

        if ($authorizationCode === '') {
            return $this->redirectToMeetingSettings()->with('error', 'Googleから認可コードを取得できませんでした。');
        }

        try {
            $action(
                $request->user(),
                $authorizationCode,
            );
        } catch (Throwable $exception) {
            Log::error('Google Calendarとの連携に失敗しました。',
                [
                    'coach_id' => $request->user()->id,
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->redirectToMeetingSettings()->with('error', 'Google Calendarとの連携に失敗しました。');
        }

        return $this->redirectToMeetingSettings()->with('success', 'Google Calendarと連携しました。');
    }

    public function destroy(Request $request, DestroyAction $action
    ): RedirectResponse {
        $action($request->user());

        return $this->redirectToMeetingSettings()->with('success', 'Google Calendarとの連携を解除しました。');
    }

    private function redirectToMeetingSettings(): RedirectResponse
    {
        return to_route(
            'settings.availability.index'
        );
    }
}
