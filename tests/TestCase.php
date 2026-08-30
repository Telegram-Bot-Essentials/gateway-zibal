<?php

declare(strict_types=1);

namespace TelegramBotEssentials\GatewayZibal\Tests;

use TelegramBotEssentials\Billing\TbeBillingServiceProvider;
use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\GatewayZibal\TbeGatewayZibalServiceProvider;
use TelegramBotEssentials\Settings\TbeSettingsServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeSettingsServiceProvider::class,
            TbeBillingServiceProvider::class,
            TbeGatewayZibalServiceProvider::class,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The zibal merchant key is stored as a SENSITIVE setting, which
        // settings() encrypts on write - that needs an app key.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
