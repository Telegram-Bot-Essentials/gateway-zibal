<?php

namespace TelegramBotEssentials\GatewayZibal\Services\Methods;

use TelegramBotEssentials\GatewayZibal\Services\ZibalMethod;

class PaymentRequest extends ZibalMethod
{
    protected string $url = 'https://gateway.zibal.ir/v1/request';

    public function __construct(int $amount, string $callbackUrl)
    {
        parent::__construct();

        $this->data['amount'] = $amount;
        $this->data['callbackUrl'] = $callbackUrl;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;

        return $this;
    }

    public function orderId(string $orderId): self
    {
        $this->data['orderId'] = $orderId;

        return $this;
    }

    public function mobile(string $mobile): self
    {
        $this->data['mobile'] = $mobile;

        return $this;
    }

    public function allowedCards(array $allowedCards): self
    {
        $this->data['allowedCards'] = $allowedCards;

        return $this;
    }

    public function ledgerId(string $ledgerId): self
    {
        $this->data['ledgerId'] = $ledgerId;

        return $this;
    }

    public function nationalCode(string $nationalCode): self
    {
        $this->data['nationalCode'] = $nationalCode;

        return $this;
    }

    public function checkMobileWithCard(string $checkMobileWithCard): self
    {
        $this->data['checkMobileWithCard'] = $checkMobileWithCard;

        return $this;
    }
}
