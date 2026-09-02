# Telegram Bot Essentials — Gateway: Zibal

[![Latest Version](https://img.shields.io/packagist/v/telegram-bot-essentials/gateway-zibal.svg)](https://packagist.org/packages/telegram-bot-essentials/gateway-zibal)
[![tests](https://github.com/Telegram-Bot-Essentials/gateway-zibal/actions/workflows/tests.yml/badge.svg)](https://github.com/Telegram-Bot-Essentials/gateway-zibal/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Integrates [Zibal](https://zibal.ir) — an Iranian online payment gateway — with
[`telegram-bot-essentials/billing`](https://github.com/Telegram-Bot-Essentials/billing).
Unlike [`gateway-card`](https://github.com/Telegram-Bot-Essentials/gateway-card), this is a
fully automated, redirect-based gateway: the buyer is sent to a real payment page and the
invoice is marked paid automatically via a server-to-server callback.

It's the template to follow for integrating any other redirect-based payment provider.

## Installation

```bash
composer require telegram-bot-essentials/gateway-zibal
php artisan migrate
```

Configure per-bot via [Settings](https://github.com/Telegram-Bot-Essentials/settings):

| Setting key | Type | Purpose |
|---|---|---|
| `billing.gateways.zibal.status` | `CHECKBOX` | Master on/off switch |
| `billing.gateways.zibal.merchant` | `SENSITIVE` | Zibal merchant key (encrypted at rest) |

Both must be set for the gateway to appear on the invoice screen.

## Flow

Zibal payment happens on Zibal's own web page, so this gateway is driven by HTTP routes,
not CallbackQueries/StateAnswers:

1. Buyer taps the invoice's "Pay with Zibal" button (an external URL) →
   `GatewayZibalController::pay()` converts the price to Rial, requests a payment, creates a
   `ToZibalAttempt`, and redirects to Zibal.
2. Buyer pays on Zibal's page → Zibal redirects back to
   `GatewayZibalController::callback()`, which calls `zibal()->verify()` and, on success,
   `attemptSucceed()` → `Invoice::markAsPaid()`, then renders a "back to bot" deep link.

Because the callback arrives outside any Telegram webhook, the controller re-establishes
tenancy and the webhook context before touching billing state — see the docs for the exact
pattern.

## Documentation

Full documentation — the `Zibal` service, the `ToZibalAttempt` model, and a side-by-side
comparison with Gateway: Card — lives on the Telegram Bot Essentials documentation site
under **Modules → Gateway: Zibal**.

## License

[MIT](LICENSE).
