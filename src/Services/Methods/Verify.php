<?php

namespace TelegramBotEssentials\GatewayZibal\Services\Methods;

use TelegramBotEssentials\GatewayZibal\Services\ZibalMethod;

class Verify extends ZibalMethod
{
    protected string $url = 'https://gateway.zibal.ir/v1/verify';

    public function __construct(string $trackId)
    {
        parent::__construct();

        $this->data['trackId'] = $trackId;
    }
}
