<?php

namespace TelegramBotEssentials\GatewayZibal\Services;


use TelegramBotEssentials\GatewayZibal\Services\Methods\PaymentRequest;
use TelegramBotEssentials\GatewayZibal\Services\Methods\Verify;

class Zibal
{
    public function paymentRequest(int $amount, string $callbackUrl): PaymentRequest
    {
        return new PaymentRequest($amount, $callbackUrl);
    }

    public function verify(string $trackId): Verify
    {
        return new Verify($trackId);
    }
}
