<?php

namespace TelegramBotEssentials\GatewayZibal\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Telegram\Bot\Api;
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
    function pay(string $token, Request $request)
    {
        $invoice = Invoice::where('public_token', $token)->firstOrFail();
        $this->initializeWHookByInvoice($invoice);

        $priceInRial = priceIn($invoice->price)->toIRR();

        $result = zibal()->paymentRequest($priceInRial, route('invoice.zibal.callback', ['token' => $token]))->execute();

        if($result['result'] != 100) {
            tbeLog('gateway-zibal')->warning('Zibal rejected payment request: ' . ($result['message'] ?? 'error message is not provided'), [
                'invoice_id' => $invoice->getKey(),
                'result_code' => $result['result'] ?? null,
                'amount' => $priceInRial,
            ]);
            return apiResponse()->error('failed to pay');
        }

        $zibalAttempt = ToZibalAttempt::create([
            'track_id' => $result['trackId'],
            'amount' => $priceInRial
        ]);

        tbeLog('gateway-zibal')->info('Zibal payment initiated', [
            'invoice_id' => $invoice->getKey(),
            'track_id' => $zibalAttempt->track_id,
            'amount' => $priceInRial,
        ]);

        billing()->attemptPayment($invoice, $zibalAttempt);

        return redirect('https://gateway.zibal.ir/start/' . $zibalAttempt->track_id);
    }

    /**
     * @param string $token
     * @param Request $request
     * @return Factory|View|JsonResponse|\Illuminate\View\View
     * @throws ConnectionException
     * @throws FeatureIsDisabled
     * @throws TelegramSDKException
     * @throws TenantCouldNotBeIdentifiedById
     */
    function callback(string $token, Request $request)
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
            $api = new Api($invoice->bot->bot_token);
            $me = $api->getMe();
            $username = $me->username;
        } catch (TelegramSDKException $e) {
            tbeLog('gateway-zibal')->error('Failed to resolve bot username for payment redirect: ' . $e->getMessage(), ['exception' => $e, 'invoice_id' => $invoice->getKey()]);
            return apiResponse()->error('Payment was successful, but unable to redirect to Telegram', 200);
        }

        $botLink = 'https://t.me/' . $username . '?start=invoice_' . $invoice->id;

        if($invoice->status == 'paid'){
            return view('tbe-gateway-zibal::result', [
                'success' => 1,
                'invoice' => $invoice,
                'botLink' => $botLink,
            ]);
        }

        if(($result['status'] ?? null) != 1) {
            tbeLog('gateway-zibal')->warning('Zibal verify failed: ' . ($result['message'] ?? 'error message is not provided'), [
                'invoice_id' => $invoice->getKey(),
                'track_id' => $request->input('trackId'),
                'status' => $result['status'] ?? null,
            ]);
            return view('tbe-gateway-zibal::result', [
                'success' => 0,
                'invoice' => $invoice,
                'botLink' => $botLink,
            ]);
        }

        if(
            !($invoice->paymentAttempt instanceof ToZibalAttempt) ||
            !($invoice->paymentAttempt instanceof PaymentAttempt)
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

        tbeLog('gateway-zibal')->info('Zibal payment verified', [
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
        wHook()->setApi(new Api($invoice->bot->bot_token));
        wHook()->setUser($invoice->botUser);
        wHook()->setUpdate(Update::make(request()->all()));
    }
}
