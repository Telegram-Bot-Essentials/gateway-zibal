<?php

declare(strict_types=1);

/*
 * Larastan boots the package's Laravel app and resolves its container
 * bindings while analysing (to type `app()`/`resolve()` calls). Some
 * essence-backed services read the per-bot settings in their constructor,
 * which reaches for the active webhook's bot. Analysis never runs inside a
 * webhook, so that lookup throws WebhookAuthException; essence's
 * ExceptionHandler::handle() then falls back to notifying the user, calls
 * wHook()->update() again, throws again, and recurses into itself until the
 * worker runs out of memory.
 *
 * Swap in a handler that simply rethrows for the duration of the analysis
 * so a missing webhook context surfaces as a normal (caught-by-Larastan)
 * exception instead of an infinite loop. This only affects static
 * analysis - nothing here ships.
 */

use TelegramBotEssentials\Essence\Services\ExceptionHandler;

if (function_exists('app')) {
    app()->bind(ExceptionHandler::class, fn () => new class extends ExceptionHandler
    {
        public function handle(Throwable $e): void
        {
            throw $e;
        }
    });
}
