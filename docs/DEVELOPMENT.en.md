# Development guide

[简体中文](DEVELOPMENT.md) · **English** · [Project home](../README.en.md) · [API reference](api.en.md) · [Deployment guide](DEPLOYMENT.en.md)

This guide covers local development, application conventions, and validation. Start with the [quick start](../README.en.md#quick-start) for installation. Production configuration and operations are covered in the deployment guide.

[Local development](#local-development) · [Project layout](#project-layout) · [Core conventions](#core-conventions) · [Testing](#testing) · [Contributing](#contributing)

## Local development

| Dependency     | Requirement                                                                                          |
| -------------- | ---------------------------------------------------------------------------------------------------- |
| PHP / Composer | PHP 8.5, Composer 2, Laravel's required extensions, `fileinfo`, `gd`, and the appropriate PDO driver |
| Node.js / npm  | Node.js 24.x (≥ 24.11.0), npm 11                                                                     |
| Database       | SQLite (default) or MySQL; CI uses SQLite and MySQL 8                                                |

After completing installation and account setup in the README, install the frontend development dependencies from the repository root:

```sh
npm ci
npm --prefix frontend ci
```

### Development and builds

Run the services you need in separate terminals:

| Command                | Service                                                                |
| ---------------------- | ---------------------------------------------------------------------- |
| `php artisan serve`    | Laravel, the API, and admin pages; defaults to `http://127.0.0.1:8000` |
| `npm run dev:admin`    | Vite live reload for Blade admin assets                                |
| `npm run dev:frontend` | A separate Vite server for the Vue website                             |

For Vue development, configure the API proxy in `frontend/.env.local`, then open the frontend address printed by Vite:

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

Use the Laravel address for login, registration, and admin pages. Laravel serves the built `public/frontend/index.html`; Vue source changes appear through the separate Vite server. The proxy forwards only `/api`, not `/assets`, `/storage`, or authentication routes. Verify complete image and authentication flows through the Laravel origin after building.

`composer run dev` starts Laravel, the queue listener, Pail logs, and admin Vite together. Pail requires `pcntl`; on native Windows, use the separate service commands above. Vue Vite still needs to be started separately.

```sh
npm run build
```

Builds produce `public/build/` for the admin panel and `public/frontend/` for the website. Both directories are committed with the source. Rebuild after changing assets or shared brand JSON. Use `build:admin` or `build:frontend` to build a single part.

CI rebuilds assets and runs `npm run check:build-sync` to compare them with the committed version. This check does not build assets itself; run it locally after committing source and build output. Use LF line endings as defined in [`.gitattributes`](../.gitattributes).

## Project layout

| Path                         | Responsibility                                                                   |
| ---------------------------- | -------------------------------------------------------------------------------- |
| `app/Http/`, `app/Policies/` | Requests, authentication, authorization, and responses                           |
| `app/Services/`              | Catalog writes and imports, homepage ordering, verification, and account changes |
| `app/Support/`               | Brands, queries, API fields, and URL rules                                       |
| `resources/`                 | Blade views, admin assets, and shared brand JSON                                 |
| `frontend/src/`              | Vue pages, components, routing, and presentation helpers                         |
| `routes/`                    | Web, API, and authentication routes                                              |
| `database/`                  | Migrations, factories, and local test seeds                                      |
| `tests/`                     | PHPUnit, real concurrency, and Playwright tests                                  |
| `docker/`                    | Nginx, PHP, and container startup configuration                                  |

`/api/*` exposes public read-only endpoints. `/dashboard` and `/profile` use session authentication; `/admin/*` also requires role authorization. Other public pages use Vue routing. The production web root is `public/`.

## Core conventions

### Brands and data

[brands.json](../resources/data/brands.json) defines brand names, codes, display names, aliases, logos, and legacy routes. When updating a brand, verify frontend routes, admin options, and API output together.

The saved `brand` determines ownership; `source_file` is used for import inference and provenance. For example, changing an Apple-sourced record to Xiaomi changes its brand in lists, details, recommendations, and counts. A saved unknown brand is not reassigned from its source file.

Audit historical data before applying confirmed alias corrections:

```sh
php artisan catalog:normalize-brands
php artisan catalog:normalize-brands --apply
```

`--apply` locks and rechecks records, normalizing only recognized aliases that do not conflict with a recognized source brand. Unknown brands and ownership conflicts remain for manual review, with provenance preserved.

### Product writes and imports

| Operation                                                          | Shared implementation                              |
| ------------------------------------------------------------------ | -------------------------------------------------- |
| Validation for create, edit, and import                            | [ProductData](../app/Services/ProductData.php)     |
| Transactional writes, unique slugs, and main-field synchronization | [ProductWriter](../app/Services/ProductWriter.php) |
| File parsing, batch validation, and the import transaction         | [ProductImport](../app/Services/ProductImport.php) |

The admin form's full specifications field must contain a JSON object; blank input means an empty object. Root values of `true`, `42`, `null`, strings, and arrays are invalid. Valid extension fields are retained, including the distinction between nested `{}` and `[]`. Omitting specifications during an edit preserves existing extensions; main record fields override their corresponding specification values.

Import files contain an array of objects. This example illustrates the format. Replace `id` with an unused positive integer; the import form selects publication status:

```json
[
    {
        "id": 10001,
        "company": "Xiaomi",
        "phonename": "Example Phone",
        "socname": "Example SoC",
        "price": "3999 起",
        "battery": "5000 mAh",
        "saledate": 20260901
    }
]
```

| Field or limit         | Rule                                                                                                                    |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Brand, name, processor | Text, up to 191 characters; import aliases such as `name`, `image`, and `processor` use their corresponding field rules |
| Image URL / price      | Up to 2048 / 100 characters; prices accept numbers or text                                                              |
| `id`                   | Required positive integer for imports, retained as the record ID; existing or repeated batch IDs are rejected           |
| `saledate`             | Integer or integer string in `0..99991231`; blank and `0` mean unknown                                                  |
| Battery capacity       | Integer in `0..30000`; imports also accept `5000 mAh`; `0` means unknown                                                |
| Files                  | Up to 20 JSON files, 2 MiB each and 10 MiB total; filenames up to 191 characters                                        |
| Records and nesting    | Up to 2000 records per batch; JSON depth limit 32; extension strings up to 5000 characters                              |

Missing or unrecognized import brands may be inferred from the filename; recognized explicit brands take precedence. Duplicate sources, invalid records, or write failures roll back the entire batch. Errors identify the file, record number, and field.

Preserve price text when saving and returning data. Values such as `3999 起`, overflowing numbers, or numeric text that would lose precision must not be forced into ordinary numbers. Lists, details, and recommendations share formatting. `official` links are sanitized by `SafeUrl`.

### Derived columns and search

`Product` generates `release_date`, `search_text`, and `slug_key` on save for date ordering, search, and direct detail lookup. When adding searchable or sortable fields, update the corresponding derivation methods and tests. Backfill historical data through new migrations or dedicated commands.

Search goes through `Product::scopeSearch()`. The default `CATALOG_SEARCH_DRIVER=like` performs substring matching on `search_text`. The `fulltext` driver uses a MySQL ngram FULLTEXT index and requires completed migrations. Non-MySQL connections and terms shorter than two characters fall back to LIKE. Both drivers share brand and chipset alias expansion, but their results may differ.

Base performance changes on real workloads and query plans. Substring matching, brand normalization expressions, and ordering all affect index use.

### Pagination and detail lookup

[PhoneQuery](../app/Support/PhoneQuery.php) orders records in the database before pagination: dated records first, date descending, name ascending, then ID ascending. Unknown dates (`null` and `0`) sort last. Page size does not change the order of the same result set.

Cursor pagination uses Laravel `cursorPaginate`, query aliases to normalize unknown dates, and `ListCursor` to adapt legacy tokens. Both page and cursor modes retain total-count queries. Detail lookup queries normalized `slug_key` directly and selects the smallest ID for duplicate keys. Response headers, metadata, aliases, and legacy field spellings are API contracts; see the [API reference](api.en.md).

[PhoneFields](../app/Support/PhoneFields.php) centralizes device field mapping. When adding a public field, check each endpoint's allowed fields, defaults, and request aliases.

### Layout and navigation

- The Vue website uses Bootstrap; Blade uses Tailwind and existing shared styles. Both use [shared-navigation.css](../resources/css/shared-navigation.css) for navigation dimensions, container widths, and theme variables.
- Admin pages use top navigation and shared containers for content, headings, and forms. The theme follows `prefers-color-scheme` without separately storing a manual preference.
- Device cards use real links through `PhoneCard`. Use `PhoneImage` for image fallbacks and `frontend/src/utils/phone.js` for price and battery formatting.
- Brand lists and brand searches keep separate request state and load 24 records at a time. Reset the relevant cursor and cancel stale requests when filters change. The admin picker fetches up to 20 results and retains selected items.
- Reuse `resources/js/http.js` and `latest-request.js` for HTTP states, cancellation, and stale responses. Homepage sections fail independently; detail pages distinguish missing records, rate limits, and network failures. Dispose of requests, timers, and carousel instances when leaving a page.

### Admin forms and controls

Reuse control variables and `admin-*` classes from `resources/css/app.css`, avoiding page-specific fixed dimensions or color overrides. Keep page headings and forms in matching containers, and retain labels, help text, and error areas.

`admin-feedback` displays submission results. Multi-record pages isolate old input and validation errors by form identifier. Checkboxes use the shared component and `$request->boolean('is_active')`; an unchecked value is saved as disabled.

Slides and featured devices share [HomepageOrder](../app/Services/HomepageOrder.php), which reads, locks, and updates ordering within a transaction. Keep ordering rules in this service.

### Uploads and storage

Slides use the `public` disk and are stored under `storage/app/public/homepage/`, exposed through `/storage/homepage/`. The server validates actual MIME content, limits each dimension to 4000 pixels and the total to 10 million pixels, then re-encodes with GD and generates a safe extension. A storage change must update both server writes and public access configuration.

`ImageUrl` centralizes image addresses, while `SafeUrl` handles clickable links. Only permitted local paths and HTTP(S) addresses are accepted; images cannot downgrade HTTPS pages to HTTP. Failed device images fall back to `/assets/logo.png`; failed brand images are hidden or replaced by the brand name.

[ManagedImage](../app/Services/ManagedImage.php) removes only application-managed homepage images confirmed to be unreferenced, including checks for same-origin absolute URL references. External images are neither downloaded nor deleted. Migrate legacy slide files with:

```sh
php artisan homepage-slides:migrate-storage
```

The optional `--delete-source` removes old files only after copying, verification, and database reference updates succeed.

### Permissions

| Role     | Capabilities                                                                                              |
| -------- | --------------------------------------------------------------------------------------------------------- |
| `user`   | Read-only dashboard and personal profile management                                                       |
| `editor` | Adds device, import, featured-device, and slide management                                                |
| `admin`  | Adds site settings and user management; may change roles, status, and verification only for users/editors |
| `owner`  | Manages other privileged accounts, subject to self-protection and the last-owner rule                     |

Roles are `user/editor/admin/owner`; status is `active/suspended`. Server middleware and policies enforce authorization. Menus only reflect permissions. Suspended accounts cannot sign in, and existing sessions are invalidated on the next protected request.

[OwnerGuard](../app/Services/OwnerGuard.php) locks current accounts and the active-owner set within a transaction. An existing active-owner set must never become empty; initializing the first owner is allowed. Role, status, and manual verification changes recheck authorization after locking. Self-deletion checks the last-owner constraint after locking the account. Profile updates lock the current email address so a new address cannot inherit verification from the old one.

### Initial owner and user management

Register an account before promoting it; no owner is created automatically:

```sh
php artisan user:promote owner@example.com --role=owner
```

The account must exist. `--role` accepts all four roles. Use `--force` for non-interactive execution; it only skips confirmation and retains last-owner protection. Web forms prevent changing your own role or suspending yourself. Manual verification changes cannot target yourself or any owner.

### Site settings and email verification

`registration_email_verification` at `/admin/settings` defaults to disabled. With it disabled, new accounts are marked verified and signed in immediately. When enabled, unverified non-owner accounts complete a guest verification-code flow before receiving a login session. Toggling the setting does not rewrite existing verification states; owners are always exempt from forced verification.

| Verification rule  | Value                                                            |
| ------------------ | ---------------------------------------------------------------- |
| Code               | Six digits, stored only as a hash and bound to the email address |
| Lifetime           | 10 minutes                                                       |
| Incorrect attempts | Up to five per code; the code is invalidated at the limit        |
| Resend interval    | 60 seconds per account, counted after a successful send          |

[EmailVerification](../app/Services/EmailVerification.php) uses one shared atomic lock per account for issuance, attempt counting, and consumption. Successful consumption occurs only once. Incorrect attempts retain the original expiry; failed resends preserve the previous code. Lock-wait timeouts return a retryable failure.

Production caches must share data and atomic locks across processes. The default uses database `cache` and `cache_locks` tables. Configure real SMTP before enabling verification; `log`, `array`, and `null` mail drivers do not deliver messages. Verification mail is sent synchronously. Send failures preserve the account and allow retries. Password resets continue to use Laravel's Password broker.

## Testing

| Command                      | Scope                                                 |
| ---------------------------- | ----------------------------------------------------- |
| `composer test`              | Clear configuration cache, then run PHPUnit           |
| `php vendor/bin/pint --test` | PHP formatting                                        |
| `npm run check`              | Distribution boundaries, ESLint, Prettier, and Vitest |
| `npm run build`              | Both production asset builds                          |
| `npm run test:browser`       | Browser interactions and layout                       |
| `npm run check:build-sync`   | Compare build output with committed assets            |

PHPUnit defaults to in-memory SQLite, but external environment variables and cached configuration can change the connection. The full suite runs migrations and data cleanup: **use only a dedicated test database**. Clear cached configuration before invoking `php vendor/bin/phpunit` directly.

Catalog tests reuse `Product::factory()` and the `draft()` state. Cover equal dates, unknown dates, duplicate names, text prices, invalid imports, and historical brands. Real concurrency tests launch separate PHP processes with shared database caches and locks. They use isolated on-disk SQLite by default, or randomly prefixed MySQL tables.

### MySQL and concurrency

Create a dedicated test database and account first. In a new terminal, replace the connection details below. Set `DB_URL` to Laravel's null literal, `(null)`, so a URL from `.env` cannot override the individual settings:

```sh
php artisan config:clear
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=catalog_test DB_USERNAME=catalog_test DB_PASSWORD='your-test-password' \
DB_URL='(null)' CONCURRENCY_DB_CONNECTION=mysql CATALOG_SEARCH_DRIVER=fulltext \
php vendor/bin/phpunit
```

<details>
<summary>PowerShell</summary>

```powershell
php artisan config:clear
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'catalog_test'
$env:DB_USERNAME = 'catalog_test'
$env:DB_PASSWORD = 'your-test-password'
$env:DB_URL = '(null)'
$env:CONCURRENCY_DB_CONNECTION = 'mysql'
$env:CATALOG_SEARCH_DRIVER = 'fulltext'
php vendor/bin/phpunit
```

</details>

Set `CONCURRENCY_DB_CONNECTION=mysql` explicitly; otherwise the concurrency suite continues to use SQLite. Its table isolation does not make the full suite safe to run against business data. Docker entrypoint tests require POSIX `sh`; they skip when it is unavailable and run in Linux CI.

### Browser regression tests

After installing dependencies and building assets, run:

```sh
npx playwright install chromium
npm run test:browser
```

The [Playwright configuration](../playwright.config.mjs) creates a temporary SQLite database and fixtures, then starts a dedicated PHP service on port `8765` by default. Tests cover search, pagination, brand changes, detail navigation, failed sections, carousel disposal, mobile layouts, and isolated form backfill. Failure screenshots and traces are saved under `results/` in the temporary directory.

| Optional environment variable    | Purpose                                                                            |
| -------------------------------- | ---------------------------------------------------------------------------------- |
| `PHP_BINARY`                     | PHP CLI path                                                                       |
| `PLAYWRIGHT_CHROMIUM_EXECUTABLE` | Path to an installed Chrome / Chromium executable                                  |
| `BROWSER_TEST_PORT`              | An available local port                                                            |
| `BROWSER_TEST_RUNTIME`           | Absolute path to a dedicated temporary directory; created automatically by default |

## Contributing

- Run checks relevant to your changes, add regression coverage for behavior changes, and update both language versions of the documentation.
- Resolve dependencies through Composer/npm and commit the relevant `composer.lock`, `package-lock.json`, and `frontend/package-lock.json`. Preserve upstream version and platform constraints.
- Add new migrations for schema changes. Preserve historical migrations, legacy routes, field spellings, and API compatibility.
- Keep environment files, databases, uploaded data, private catalogs, credentials, and local audit output out of version control. `npm run check:open-source` checks tracked files against distribution boundaries.

For dependency updates, also run:

```sh
composer validate --strict
composer check-platform-reqs
composer audit
npm audit --audit-level=high
npm --prefix frontend audit --audit-level=high
```

[CI](../.github/workflows/ci.yml) checks PHP, frontend code, build consistency, browsers, MySQL, Docker startup, and secrets. GitHub Actions are pinned to commit SHAs and updated by Dependabot.

## Deployment

See the [deployment guide](DEPLOYMENT.en.md) for first-time Docker setup, manual releases, Nginx examples, CSP, backups, and updates.
