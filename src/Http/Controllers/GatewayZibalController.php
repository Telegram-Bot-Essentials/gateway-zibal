<?php

namespace TelegramBotEssentials\GatewayZibal\Http\Controllers;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\GatewayZibal\Models\ToZibalAttempt;

class GatewayZibalController extends Controller
{
    /**
     * @throws FeatureIsDisabled
     * @throws ConnectionException
     * @throws TenantCouldNotBeIdentifiedById
     * @throws TelegramSDKException
     */
    public function pay(string $token, Request $request)
    {
        $invoice = Invoice::where('public_token', $token)->firstOrFail();
        $this->initializeWHookByInvoice($invoice);

        $priceInRial = priceIn($invoice->price)->toIRR();

        $result = zibal()->paymentRequest($priceInRial, route('invoice.zibal.callback', ['token' => $token]))->execute();

        $code = $this->field($result, 'result');
        if ($code !== '100') {
            $zibalMessage = $this->field($result, 'message') ?? 'no message';
            tbeLog('gateway-zibal')->for($invoice->botUser)->warning('Zibal refused to start payment of invoice #{invoice_id} ({amount} IRR): [{result_code}] {zibal_message}', [
                'invoice_id' => $invoice->getKey(),
                'result_code' => $code,
                'zibal_message' => $zibalMessage,
                'amount' => $priceInRial,
            ]);

            // 102-104: the merchant id is unknown, inactive or invalid - no
            // member can pay through Zibal until the owner fixes it.
            if (in_array($code, ['102', '103', '104'], true)) {
                ownerAlert('gateway-zibal.merchant', fn () => __('tbe-gateway-zibal::settings.alerts.merchant', [
                    'code' => $code,
                    'message' => $zibalMessage,
                ]));
            }

            return tbeApiResponse()->error('failed to pay');
        }

        $zibalAttempt = ToZibalAttempt::create([
            'track_id' => $result['trackId'],
            'amount' => $priceInRial,
        ]);

        tbeLog('gateway-zibal')->for($invoice->botUser)->info('Zibal payment {track_id} started for invoice #{invoice_id}: {amount} IRR', [
            'invoice_id' => $invoice->getKey(),
            'track_id' => $zibalAttempt->track_id,
            'amount' => $priceInRial,
        ]);

        billing()->attemptPayment($invoice, $zibalAttempt);

        return redirect('https://gateway.zibal.ir/start/'.$zibalAttempt->track_id);
    }

    /**
     * @return Factory|View|JsonResponse|\Illuminate\View\View
     *
     * @throws ConnectionException
     * @throws FeatureIsDisabled
     * @throws TelegramSDKException
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function callback(string $token, Request $request)
    {
        $invoice = Invoice::where('public_token', $token)->firstOrFail();
        $this->initializeWHookByInvoice($invoice);

        $request->validate([
            'success' => 'required',
            'status' => 'required',
            'trackId' => 'required',
        ]);

        // Settle first: the customer has paid, and nothing about the way back
        // to Telegram may stand between a verified payment and the invoice.
        $success = $this->settle($invoice, (string) $request->string('trackId'));

        return view('tbe-gateway-zibal::result', [
            'success' => $success ? 1 : 0,
            'invoice' => $invoice,
            'botLink' => $this->botLink($invoice),
        ]);
    }

    /**
     * Marks the invoice paid if Zibal verified this invoice's own payment in
     * full; whether it is paid. The tracking id arrives from the browser, and
     * Zibal only says that it was paid: without the two checks here, a small
     * payment could settle a larger invoice.
     *
     * @throws ConnectionException
     */
    private function settle(Invoice $invoice, string $trackId): bool
    {
        if ($invoice->getAttribute('status') === 'paid') {
            return true;
        }

        $zibalAttempt = $invoice->paymentAttempt;

        if (! ($zibalAttempt instanceof ToZibalAttempt) || $this->text($zibalAttempt->getAttribute('track_id')) !== $trackId) {
            tbeLog('gateway-zibal')->for($invoice->botUser)->warning('Refused payment {track_id} for invoice #{invoice_id}: it is not the payment started for this invoice', [
                'invoice_id' => $invoice->getKey(),
                'track_id' => $trackId,
            ]);

            return false;
        }

        $result = zibal()->verify($trackId)->execute();

        if (($result['status'] ?? null) != 1) {
            tbeLog('gateway-zibal')->for($invoice->botUser)->warning('Zibal did not verify payment {track_id} of invoice #{invoice_id}: [{status}] {zibal_message}', [
                'invoice_id' => $invoice->getKey(),
                'zibal_message' => $this->field($result, 'message') ?? 'no message',
                'track_id' => $trackId,
                'status' => $result['status'] ?? null,
            ]);

            return false;
        }

        $received = $this->field($result, 'amount');

        $expected = $this->text($zibalAttempt->getAttribute('amount'));

        if (! is_numeric($received) || ! is_numeric($expected) || BigDecimal::of($received)->compareTo(BigDecimal::of($expected)) !== 0) {
            tbeLog('gateway-zibal')->for($invoice->botUser)->error('Refused payment {track_id} for invoice #{invoice_id}: Zibal verified {received_amount} IRR, expected {amount} IRR', [
                'invoice_id' => $invoice->getKey(),
                'track_id' => $trackId,
                'received_amount' => $received,
                'amount' => $expected,
            ]);

            return false;
        }

        $zibalAttempt->update([
            'received_amount' => $received,
        ]);

        $zibalAttempt->attemptSucceed();

        tbeLog('gateway-zibal')->for($invoice->botUser)->info('Zibal payment {track_id} verified for invoice #{invoice_id}: {received_amount} IRR', [
            'invoice_id' => $invoice->getKey(),
            'track_id' => $trackId,
            'received_amount' => $received,
        ]);

        return true;
    }

    /** The way back to the bot; null when its username cannot be resolved right now. */
    private function botLink(Invoice $invoice): ?string
    {
        $bot = $invoice->bot;
        $username = $bot instanceof Bot ? $bot->username() : null;

        $id = $invoice->getKey();

        return $username === null || ! is_scalar($id) ? null : 'https://t.me/'.$username.'?start=invoice_'.$id;
    }

    /**
     * @throws TelegramSDKException
     * @throws TenantCouldNotBeIdentifiedById
     */
    private function initializeWHookByInvoice(Invoice $invoice): void
    {
        tenancy()->initialize($invoice->bot);
        wHook()->setBot($invoice->bot);
        wHook()->setApi(telegramApi($this->botToken($invoice)));
        wHook()->setUser($invoice->botUser);
        wHook()->setUpdate(Update::make(request()->all()));
    }

    /** A scalar field of a Zibal response, as a string; null when absent. */
    private function field(mixed $response, string $key): ?string
    {
        $value = is_array($response) ? ($response[$key] ?? null) : null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function botToken(Invoice $invoice): string
    {
        $token = $invoice->bot?->getAttribute('bot_token');

        return is_string($token) ? $token : '';
    }
}
