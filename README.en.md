# Smartphone Catalog

[简体中文](README.md) · **English**

[![CI](https://github.com/GeneralPeople1970/smartphone-catalog/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/GeneralPeople1970/smartphone-catalog/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

An open-source smartphone specifications catalog built with Laravel and Vue. It includes a public website, an admin panel, and a read-only API for running your own device catalog.

[Quick start](#quick-start) · [Deployment](#deployment) · [Development guide](docs/DEVELOPMENT.en.md) · [API reference](docs/api.en.md)

## Features

- **Device browsing**: brand directories, keyword search, specifications, and on-demand pagination, with responsive layouts and a theme that follows system preferences.
- **Content management**: create, edit, publish, and unpublish devices; import JSON batches with field validation and rollback of the entire batch on failure.
- **Homepage management**: configure slides and featured devices, including their order and publication status.
- **Accounts and permissions**: user, editor, admin, and owner roles, with account status management and email verification.
- **Public API**: access brands, devices, search, and homepage data, with field selection, page-based pagination, and cursor pagination.

## Tech stack

| Layer             | Technologies                                    |
| ----------------- | ----------------------------------------------- |
| Public website    | Vue 3 · Vue Router 5 · Bootstrap 5              |
| Admin panel       | Laravel 13 · Blade · Tailwind CSS 4 · Alpine.js |
| Database          | SQLite / MySQL                                  |
| Build and testing | Vite 8 · PHPUnit · Vitest · Playwright          |

## Quick start

Requires **PHP 8.5 and Composer 2**, Laravel's required extensions, `fileinfo`, `gd`, and the PDO driver for your database. The steps below use SQLite. Built assets are included in the repository, so Node.js is not required to run the application.

```sh
git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
composer install
cp .env.example .env
php artisan key:generate
composer run setup
php artisan storage:link
php artisan serve
```

`composer run setup` creates the SQLite file and runs migrations. To use MySQL, configure `.env` before this step. Set `APP_URL` to the address you use to access the site; the local server defaults to `http://127.0.0.1:8000`.

Open the [local site](http://127.0.0.1:8000) and create an account on the [registration page](http://127.0.0.1:8000/register). In another terminal, promote that account to owner, replacing the example email with the one you registered:

```sh
php artisan user:promote owner@example.com --role=owner
```

Use the [dashboard](http://127.0.0.1:8000/dashboard) to manage content. A fresh installation has no device data; add devices manually or import JSON in the admin panel. See the [import guide](docs/DEVELOPMENT.en.md#product-writes-and-imports) for formats and limits.

## Deployment

The recommended setup uses **Docker Engine and Docker Compose v2** with [prebuilt images](https://hub.docker.com/r/generalpeople/smartphone-catalog). From a fresh clone, prepare the environment:

```sh
cp .env.docker.example .env
docker compose -f compose.deploy.yml run --rm --no-deps app php artisan key:generate --show
```

Save the output as `APP_KEY` in `.env`, set `APP_URL`, and choose different strong passwords for `DB_PASSWORD` and `DB_ROOT_PASSWORD`. Keep `SESSION_SECURE_COOKIE=true` for HTTPS; set it to `false` when serving over HTTP. Then start the services:

```sh
docker compose -f compose.deploy.yml up -d --pull always --wait
```

The default port is `8080`. Create an account at your site's `/register` page, then promote it inside the container, replacing the example email:

```sh
docker compose -f compose.deploy.yml exec app php artisan user:promote owner@example.com --role=owner
```

Deployment runs database migrations and waits for health checks. Database files and uploads persist in named volumes. See the [deployment guide](docs/DEPLOYMENT.en.md) for updates, backups, version pinning, reverse proxies, and manual deployment.

## Development

Editing frontend assets requires **Node.js 24.x (≥ 24.11.0) and npm 11**. Install both sets of dependencies from the repository root:

```sh
npm ci
npm --prefix frontend ci
```

| Command                | Purpose                                                                          |
| ---------------------- | -------------------------------------------------------------------------------- |
| `composer test`        | Backend tests                                                                    |
| `npm run check`        | Open-source boundary checks, frontend linting, formatting checks, and unit tests |
| `npm run build`        | Build public website and admin assets                                            |
| `npm run test:browser` | Browser interaction and layout regression tests                                  |

Run `npx playwright install chromium` before the first browser test. After changing frontend assets, rebuild and commit both `public/build/` and `public/frontend/` with the source changes. CI checks that committed assets match the source.

See the [development guide](docs/DEVELOPMENT.en.md) for live reload, project structure, business rules, and detailed testing instructions, and the [API reference](docs/api.en.md) for endpoints, parameters, and response formats.

## Contributing

Use [Issues](https://github.com/GeneralPeople1970/smartphone-catalog/issues) to report bugs or discuss improvements, and submit changes through pull requests. Describe the purpose of your change and run the relevant checks.

## License

The code is licensed under [MIT](LICENSE). Brand names and logos belong to their respective owners; permission to use or redistribute third-party assets must be confirmed separately. This project is not affiliated with or endorsed by any device manufacturer.
