<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\GatewayZibal\Models\ToZibalAttempt;
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

function pendingZibalPayment(Bot $bot, int $peerId, string $trackId): Invoice
{
    $invoice = zibalInvoice($bot, $peerId);
    wHook()->setBot($bot);
    $attempt = ToZibalAttempt::create(['track_id' => $trackId, 'amount' => '500000']);
    billing()->attemptPayment($invoice, $attempt);
    wHook()->clear();

    return $invoice;
}

function fakeZibalVerified(string $telegramUrl, mixed $telegramResponse): void
{
    $factory = new Factory;
    $factory->fake([
        'gateway.zibal.ir/*' => Http::response(['result' => 100, 'status' => 1, 'amount' => 500000, 'message' => 'success']),
        $telegramUrl => $telegramResponse,
        '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1, 'date' => 1, 'chat' => ['id' => 1, 'type' => 'private']]]),
    ]);
    Http::swap($factory);
}

it('marks a verified payment paid even when telegram cannot be reached for the redirect link', function () {
    fakeZibalVerified('api.telegram.org/*/getMe', Http::failedConnection());
    $invoice = pendingZibalPayment($this->bot, 7003, '111222');

    $response = $this->get(route('invoice.zibal.callback', ['token' => $invoice->public_token, 'success' => 1, 'status' => 2, 'trackId' => '111222']));

    $response->assertOk();
    expect($invoice->refresh()->status)->toBe('paid');
});

it('links back to the bot once a verified payment is paid', function () {
    fakeZibalVerified('api.telegram.org/*/getMe', Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Shop', 'username' => 'shop_bot']]));
    $invoice = pendingZibalPayment($this->bot, 7004, '333444');

    $response = $this->get(route('invoice.zibal.callback', ['token' => $invoice->public_token, 'success' => 1, 'status' => 2, 'trackId' => '333444']));

    $response->assertOk()->assertSee('https://t.me/shop_bot?start=invoice_'.$invoice->id, false);
    expect($invoice->refresh()->status)->toBe('paid');
});

it('refuses a tracking id that does not belong to the invoice, without asking zibal', function () {
    fakeZibalVerified('api.telegram.org/*/getMe', Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Shop', 'username' => 'shop_bot']]));
    $invoice = pendingZibalPayment($this->bot, 7005, '555666');

    $this->get(route('invoice.zibal.callback', ['token' => $invoice->public_token, 'success' => 1, 'status' => 2, 'trackId' => 'paid-for-another-invoice']))->assertOk();

    expect($invoice->refresh()->status)->toBe('pending');
    Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), 'gateway.zibal.ir/v1/verify'));
});

it('refuses a payment zibal verified for a different amount', function () {
    $factory = new Factory;
    $factory->fake([
        'gateway.zibal.ir/*' => Http::response(['result' => 100, 'status' => 1, 'amount' => 1000, 'message' => 'success']),
        '*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Shop', 'username' => 'shop_bot', 'message_id' => 1, 'date' => 1, 'chat' => ['id' => 1, 'type' => 'private']]]),
    ]);
    Http::swap($factory);
    $invoice = pendingZibalPayment($this->bot, 7006, '777888');

    $this->get(route('invoice.zibal.callback', ['token' => $invoice->public_token, 'success' => 1, 'status' => 2, 'trackId' => '777888']))->assertOk();

    expect($invoice->refresh()->status)->toBe('pending');
});

it('refuses a payment zibal verified without saying how much', function () {
    $factory = new Factory;
    $factory->fake([
        'gateway.zibal.ir/*' => Http::response(['result' => 100, 'status' => 1, 'message' => 'success']),
        '*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Shop', 'username' => 'shop_bot', 'message_id' => 1, 'date' => 1, 'chat' => ['id' => 1, 'type' => 'private']]]),
    ]);
    Http::swap($factory);
    $invoice = pendingZibalPayment($this->bot, 7007, '999000');

    $this->get(route('invoice.zibal.callback', ['token' => $invoice->public_token, 'success' => 1, 'status' => 2, 'trackId' => '999000']))->assertOk();

    expect($invoice->refresh()->status)->toBe('pending');
});

it('does not ask zibal again for an invoice that is already paid', function () {
    fakeZibalVerified('api.telegram.org/*/getMe', Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Shop', 'username' => 'shop_bot']]));
    $invoice = pendingZibalPayment($this->bot, 7008, '121212');
    $callback = route('invoice.zibal.callback', ['token' => $invoice->public_token, 'success' => 1, 'status' => 2, 'trackId' => '121212']);

    $this->get($callback)->assertOk();
    $this->get($callback)->assertOk()->assertSee('Payment Successful');

    expect(Http::recorded(fn ($request) => str_contains((string) $request->url(), 'gateway.zibal.ir/v1/verify')))->toHaveCount(1);
});
