<?php

declare(strict_types=1);

use TelegramBotEssentials\Billing\Services\Gateways;
use TelegramBotEssentials\GatewayZibal\Services\Zibal;
use TelegramBotEssentials\Settings\Services\Settings;

it('registers the zibal gateway settings under the billing tree', function () {
    $keys = app(Settings::class)->getSettings()->keys();

    expect($keys)->toContain(
        'billing.gateways.zibal',
        'billing.gateways.zibal.status',
        'billing.gateways.zibal.merchant',
    );
});

it('exposes a zibal payment gateway to billing', function () {
    $keys = app(Gateways::class)->getGateways()->pluck('key');

    expect($keys)->toContain('zibal');
});

it('registers the hosted payment routes', function () {
    expect(route('invoice.zibal.pay', ['token' => 'abc']))->toContain('/invoice/abc/zibal/pay')
        ->and(app('router')->has('invoice.zibal.callback'))->toBeTrue();
});

it('resolves the zibal service helper', function () {
    expect(zibal())->toBeInstanceOf(Zibal::class);
});
