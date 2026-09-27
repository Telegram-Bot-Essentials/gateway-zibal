<?php

namespace TelegramBotEssentials\GatewayZibal\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\Billing\Models\Abstract\PaymentAttempt;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
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
                adminAlert('gateway-zibal.merchant', fn () => __('tbe-gateway-zibal::settings.alerts.merchant', [
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

        $result = zibal()->verify($request->input('trackId'))->execute();

        try {
            $api = telegramApi($this->botToken($invoice));
            $me = $api->getMe();
            $username = $me->username;
        } catch (TelegramSDKException $e) {
            tbeLog('gateway-zibal')->for($invoice->botUser)->error('Invoice #{invoice_id} paid, but the bot username for the redirect could not be resolved: '.$e->getMessage(), ['exception' => $e, 'invoice_id' => $invoice->getKey()]);

            return tbeApiResponse()->error('Payment was successful, but unable to redirect to Telegram', 200);
        }

        $botLink = 'https://t.me/'.$username.'?start=invoice_'.$invoice->id;

        if ($invoice->status == 'paid') {
            return view('tbe-gateway-zibal::result', [
                'success' => 1,
                'invoice' => $invoice,
                'botLink' => $botLink,
            ]);
        }

        if (($result['status'] ?? null) != 1) {
            tbeLog('gateway-zibal')->for($invoice->botUser)->warning('Zibal did not verify payment {track_id} of invoice #{invoice_id}: [{status}] {zibal_message}', [
                'invoice_id' => $invoice->getKey(),
                'zibal_message' => $this->field($result, 'message') ?? 'no message',
                'track_id' => $request->input('trackId'),
                'status' => $result['status'] ?? null,
            ]);

            return view('tbe-gateway-zibal::result', [
                'success' => 0,
                'invoice' => $invoice,
                'botLink' => $botLink,
            ]);
        }

        if (
            ! ($invoice->paymentAttempt instanceof ToZibalAttempt) ||
            ! ($invoice->paymentAttempt instanceof PaymentAttempt)
        ) {
            return view('tbe-gateway-zibal::result', [
                'success' => 0,
                'invoice' => $invoice,
                'botLink' => $botLink,
            ]);
        }

        $zibalAttempt = $invoice->paymentAttempt;
        $zibalAttempt->update([
            'received_amount' => $result['amount'],
        ]);

        $zibalAttempt->attemptSucceed();

        tbeLog('gateway-zibal')->for($invoice->botUser)->info('Zibal payment {track_id} verified for invoice #{invoice_id}: {received_amount} IRR', [
            'invoice_id' => $invoice->getKey(),
            'track_id' => $zibalAttempt->track_id,
            'received_amount' => $result['amount'],
        ]);

        return view('tbe-gateway-zibal::result', [
            'success' => 1,
            'invoice' => $invoice,
            'botLink' => $botLink,
        ]);
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

    private function botToken(Invoice $invoice): string
    {
        $token = $invoice->bot?->getAttribute('bot_token');

        return is_string($token) ? $token : '';
    }
}
