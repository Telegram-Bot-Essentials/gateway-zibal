# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Until the API
stabilizes at 1.0 a `0.0.x` bump may carry breaking changes.

## [Unreleased]

### Changed

- Accepts essence 0.16 alongside 0.15.

## [0.0.14] - 2026-09-27

### Changed

- Requires essence `^0.15` for `adminAlert()` and `tbeLog()->for()`.
- Zibal refusing a payment because of the merchant id (codes 102-104:
  unknown, inactive, invalid) alerts the owner and admins (throttled): no
  member can pay through Zibal until it is fixed.
- Log messages name the invoice, track id and amount, and are bound to the
  invoice's user, since the pay and callback requests come from a browser
  rather than a Telegram update.

### Fixed

- The pay and callback routes built their Telegram client with `new Api()`,
  bypassing essence's `telegramApi()` and its HTTP client.

## [0.0.13] - 2026-09-22

### Changed

- Accepts `telegram-bot-essentials/essence` `^0.14` as well as `^0.13`:
  0.14.0 only removed `DoneLimited`/`CannotSetItAsDone`/`HidesDone`, none of
  which this package uses.

## [0.0.12] - 2026-09-20

### Changed

- **BREAKING:** requires `telegram-bot-essentials/essence` `^0.13` (the JSON user
  state and the forms engine). No code change: the package's suite passes
  against essence 0.13.0.

## [0.0.11] - 2026-09-01

### Changed

- **BREAKING:** requires `telegram-bot-essentials/essence` `^0.12`.

### Added

- Pest test suite, Laravel Pint, Larastan (level max), GitHub Actions CI,
  Laravel Workbench, `LICENSE` (MIT) and this changelog.

### Fixed

- The payment-callback controller uses essence's `tbeApiResponse()` instead
  of the removed `apiResponse()` global (0.0.10).

### Removed

- The `phpstan-bootstrap.php` `ExceptionHandler` recursion stub — essence's
  handler now guards its own fallback path.
