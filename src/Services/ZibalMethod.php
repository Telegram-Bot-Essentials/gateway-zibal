<?php

namespace TelegramBotEssentials\GatewayZibal\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;

abstract class ZibalMethod
{
    protected string $url;
    protected array $data;

    public function __construct()
    {

    }

    /**
     * @throws FeatureIsDisabled
     * @throws ConnectionException
     */
    public function execute()
    {
        $status = settings()->get('billing.gateways.zibal.status');
        $merchant = settings()->get('billing.gateways.zibal.merchant');
        dependsOn($status);
        dependsOn($merchant);
        $this->data['merchant'] = $merchant;
        $result = HTTP::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->url, $this->data);
        return $result->json();
    }
}
