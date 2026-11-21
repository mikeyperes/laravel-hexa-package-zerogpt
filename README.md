# .

ZeroGPT  — AI content detection with per-sentence probability scoring

## Identity

- Kind: Laravel support package
- Version: `2.0.10`
- Composer name: `hexawebsystems/laravel-hexa-package-zerogpt`
- PHP namespace: `hexa_package_zerogpt`
- Requires: `php` ^8.2, `hexawebsystems/laravel-hexa-core` *
- Installed: in a Hexa Core app, `packages/hexawebsystems/.`

## Features

- **ZeroGptController** (`src/Http/Controllers/ZeroGptController.php`): settings + raw test page + detect endpoint.
- **ZeroGptServiceProvider** (`src/Providers/ZeroGptServiceProvider.php`): registers config, routes, views, settings card, sidebar.
- **ZeroGptService** (`src/Services/ZeroGptService.php`): AI-content detection through ZeroGPT's text detection endpoint.

## How it is used

Pages and endpoints:

- `GET /zerogpt/settings` (`zerogpt.settings`)
- `POST /zerogpt/settings` (`zerogpt.settings.save`)
- `POST /zerogpt/test` (`zerogpt.test`)
- `GET /raw-zerogpt` (`zerogpt.raw`)
- `POST /zerogpt/detect` (`zerogpt.detect`)

## Settings

Hexa Core settings read or written by the package: `zerogpt_api_key`, `zerogpt_debug_mode`, `zerogpt_enabled`.

Config `config/zerogpt.php`: `version`, `enabled`, `api_url`, `setting_keys`.

## What it calls

- Outside services: `api.zerogpt.com`.

## Code layout

- Service provider: `hexa_package_zerogpt\Providers\ZeroGptServiceProvider`.
- Folders: `src/Http/Controllers/`, `src/Providers/`, `src/Services/`.
- Views: `resources/views/` (3 entries).

## Tests

`tests/` holds 2 test file(s). Run them from the host app with `php artisan test packages/hexawebsystems/./tests`.
