<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Settings\Services\Settings;

uses(RefreshDatabase::class);

function zibalInvoice(Bot $bot, int $peerId): Invoice
{
    $botUser = test()->makeBotUser($bot, $peerId);

    return Invoice::create([
        'bot_id' => $bot->id,
        'bot_user_id' => $botUser->id,
        'price' => '50000',
        'payable_type' => $botUser::class,
        'payable_id' => $botUser->id,
    ]);
}

function fakeZibalRequest(int $result, string $message): void
{
    // A fresh factory: the base TestCase already registered a catch-all fake,
    // and stubs registered first win.
    $factory = new Factory;
    $factory->fake([
        'gateway.zibal.ir/*' => Http::response(['result' => $result, 'message' => $message]),
        '*' => Http::response(['ok' => true, 'result' => true]),
    ]);
    Http::swap($factory);
}

beforeEach(function () {
    $this->bot = $this->makeBot();
    wHook()->setBot($this->bot);
    $settings = app(Settings::class);
    $settings->set('billing.currency', 'IRR');
    $settings->set('billing.gateways.zibal.status', true);
    $settings->set('billing.gateways.zibal.merchant', 'bad-merchant');
    wHook()->clear();
});

function ownerAlerts(Bot $bot): array
{
    return Http::recorded(fn ($request) => str_ends_with((string) $request->url(), '/sendMessage')
        && (int) $request['chat_id'] === (int) $bot->bot_owner_peer_id)
        ->map(fn ($pair) => (string) $pair[0]['text'])
        ->values()
        ->all();
}

it('alerts the owner when zibal rejects the merchant', function () {
    fakeZibalRequest(103, 'merchant is inactive');
    $invoice = zibalInvoice($this->bot, 7001);

    $this->get(route('invoice.zibal.pay', ['token' => $invoice->public_token]));

    expect(ownerAlerts($this->bot))->toBe([
        '⚠️ '.__('tbe-gateway-zibal::settings.alerts.merchant', ['code' => '103', 'message' => 'merchant is inactive']),
    ]);
});

it('does not alert the owner for a rejection that is not about the merchant', function () {
    fakeZibalRequest(105, 'amount must be larger');
    $invoice = zibalInvoice($this->bot, 7002);

    $this->get(route('invoice.zibal.pay', ['token' => $invoice->public_token]));

    expect(ownerAlerts($this->bot))->toBe([]);
});
