<?php

use TelegramBotEssentials\GatewayZibal\Services\Zibal;

if(!function_exists('zibal')) {
    function zibal(): Zibal
    {
        return app(Zibal::class);
    }
}