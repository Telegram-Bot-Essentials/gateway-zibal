<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Settings\Services\Settings;

// ZibalMethod::execute() reads the per-bot zibal settings, so point the
// webhook context at a bot first. Outbound HTTP is already faked by the
// shared TestCase.
beforeEach(function () {
    wHook()->setBot($this->makeBot());
});

it('refuses to call zibal while the gateway is disabled', function () {
    expect(fn () => zibal()->paymentRequest(50000, 'https://example.test/callback')->execute())
        ->toThrow(FeatureIsDisabled::class);

    Http::assertNothingSent();
});

it('posts the merchant and amount to the zibal request endpoint once enabled', function () {
    $settings = app(Settings::class);
    $settings->set('billing.gateways.zibal.status', true);
    $settings->set('billing.gateways.zibal.merchant', 'zibal-merchant-key');

    zibal()->paymentRequest(50000, 'https://example.test/callback')->execute();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gateway.zibal.ir/v1/request')
            && $request['merchant'] === 'zibal-merchant-key'
            && $request['amount'] === 50000
            && $request['callbackUrl'] === 'https://example.test/callback';
    });
});
