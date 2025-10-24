<?php

namespace TelegramBotEssentials\GatewayZibal;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Settings\DTOs\Setting;
use TelegramBotEssentials\Settings\Enums\SettingType;

class TbeGatewayZibalServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->registerPublishing();

        Route::prefix('')
            ->group(__DIR__ . '/../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'tbe-gateway-zibal');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'tbe-gateway-zibal');

        callbackQueryBus()->addCallbackQueries([

        ]);

        stateAnswerBus()->addStateAnswers([

        ]);

        $this->addSettings();
        $this->registerToBilling();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../lang' => resource_path('lang/vendor/tbe-gateway-zibal'),
            ], 'tbe-gateway-zibal-translations');
        }
    }

    private function addSettings(): void
    {
        settings()->addSetting(new Setting(
            key: 'billing',
            label: 'Billing',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways',
            label: 'Gateways',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.zibal',
            label: 'Zibal',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.zibal.status',
            label: 'Zibal Status',
            type: SettingType::CHECKBOX,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.gateways.zibal.merchant',
            label: 'Zibal Merchant',
            type: SettingType::SENSITIVE,
        ));
    }

    private function registerToBilling(): void
    {
        gateways()->addGateway(new Gateway(
            key: 'zibal',
            label: 'Zibal',
            inlineButtonGenerator: function (Invoice $invoice) {
                if(!settings()->get('billing.gateways.zibal.status') || !settings()->get('billing.gateways.zibal.merchant'))
                    return null;
                return Keyboard::inlineButton([
                    'text' => __('tbe-billing::invoice.summary.keys.to_zibal', [
                        'price' => number_format(priceIn($invoice->price)->toIRT())
                    ]),
                    'url' => route('invoice.zibal.pay', ['token' => $invoice->public_token])
                ]);
            }
        ));
    }
}
