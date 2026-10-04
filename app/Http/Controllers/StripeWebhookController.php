<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\MeetingQuota\Checkout\HandleCheckoutCompletedAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Webhookの署名を検証し、
 * 対応する業務処理へ引き渡す。
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, HandleCheckoutCompletedAction $action): Response
    {
        // 署名検証に使用する加工前の本文
        $payload = $request->getContent();

        // Stripeから送られる署名ヘッダー
        $signature = $request->header('Stripe-Signature');

        // stripe listenで発行されたwhsec_...
        $webhookSecret = config('services.stripe.webhook_secret');

        if (
            ! is_string($webhookSecret)
            || $webhookSecret === ''
        ) {
            throw new RuntimeException('Stripe Webhook Secretが設定されていません。');
        }

        if (
            ! is_string($signature)
            || $signature === ''
        ) {
            return response('Stripe-Signature header is missing.', 400);
        }

        try {
            /*
             * 生の本文・署名ヘッダー・Webhook Secretを使い、
             * Stripeからの正規リクエストか検証する。
             */
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (UnexpectedValueException) {
            return response('Invalid Stripe webhook payload.', 400);
        } catch (SignatureVerificationException) {
            return response('Invalid Stripe webhook signature.', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response('ok', 200);
        }

        $session = $event->data->object;

        if (! $session instanceof Session) {
            throw new RuntimeException(
                'StripeイベントにCheckout Sessionがありません。',
            );
        }

        $action($session, $event->id, $event->type);

        return response('ok', 200);
    }
}
