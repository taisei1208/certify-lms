<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingPackStatus;
use App\Http\Requests\MeetingQuota\CreateCheckoutRequest;
use App\Models\MeetingPack;
use App\UseCases\MeetingQuota\Checkout\CreateCheckoutSessionAction;
use App\UseCases\MeetingQuota\Checkout\FetchCheckoutSuccessAction;
use App\UseCases\MeetingQuota\Checkout\FetchPurchasablePacksAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingQuotaCheckoutController extends Controller
{
    public function index(FetchPurchasablePacksAction $action): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => $action(),
        ]);
    }

    public function store(CreateCheckoutRequest $request, CreateCheckoutSessionAction $action): RedirectResponse
    {
        $meetingPack = MeetingPack::query()->findOrFail($request->validated('meeting_pack_id'));

        abort_unless(
            $meetingPack->status === MeetingPackStatus::Published,
            403,
        );

        $checkoutUrl = $action(
            $request->user(),
            $meetingPack->id,
        );

        return redirect()->away($checkoutUrl);
    }

    public function success(Request $request, FetchCheckoutSuccessAction $action): View
    {
        $sessionId = $request->query('session_id');

        return view('meeting-quota.success', [
            'payment' => $action(
                $request->user(),
                is_string($sessionId) ? $sessionId : null,
            ),
        ]);
    }
}
