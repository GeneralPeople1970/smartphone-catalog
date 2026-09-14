# Deployment guide

[简体中文](DEPLOYMENT.md) · **English**

[Project overview](../README.en.md) · [Development guide](DEVELOPMENT.en.md)

Prebuilt Docker images are the recommended option. The host does not need PHP, Composer, or Node.js. The default stack includes MySQL 8, PHP-FPM, Nginx, and a one-shot migration service. Run the commands from the repository root; file-copy examples use Linux shell syntax.

## Docker deployment

### 1. Prepare the configuration

Install Docker Engine and a Docker Compose v2 release that supports `up --wait`. Start from a fresh clone:

```sh
git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
cp .env.docker.example .env
docker compose -f compose.deploy.yml run --rm --no-deps app php artisan key:generate --show
```

The last command prints a key without starting the database. Save the complete `base64:...` value as `APP_KEY` in `.env`, then configure:

| Setting                            | Value                                                                |
| ---------------------------------- | -------------------------------------------------------------------- |
| `APP_ENV` / `APP_DEBUG`            | Keep `production` / `false`                                          |
| `APP_URL`                          | The public address, such as `https://catalog.example.com`            |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | Two different, strong random passwords replacing the template values |
| `SESSION_SECURE_COOKIE`            | `true` for HTTPS; `false` for HTTP                                   |
| `WEB_PORT`                         | Defaults to `8080`; adjust to your host port allocation              |

For an HTTP test, use `APP_URL=http://127.0.0.1:8080`. Generate `APP_KEY` only for the first installation, and retain it across updates and restores. Keep `.env` secure and outside Git.

### 2. Start the services and create the owner

```sh
docker compose -f compose.deploy.yml up -d --pull always --wait
```

Compose waits for MySQL, applies migrations, starts the application, and waits for its health check. The command exits with a nonzero status on failure; see the diagnostics below.

Open the site at `APP_URL`, create an account at `/register`, and promote the registered account to owner:

```sh
docker compose -f compose.deploy.yml exec app php artisan user:promote owner@example.com --role=owner
```

Replace the example email with the registered address, confirm the change, and open `/dashboard`. The command changes existing accounts only; `--force` skips its interactive confirmation. A fresh installation has no device data; add devices or import JSON in the admin panel. Registration email verification is disabled by default, so initial setup does not require a mail service.

### 3. Check status and logs

```sh
docker compose -f compose.deploy.yml ps --all
curl -fsS http://127.0.0.1:8080/up
docker compose -f compose.deploy.yml logs --tail=100
```

Adjust the health-check URL if you changed `WEB_PORT`. `/up` checks that the application can boot and respond; it does not independently probe the database or mail service. After a release, also check device pages, sign-in, and uploads.

The template writes Laravel logs to a file inside the app container:

```sh
docker compose -f compose.deploy.yml exec app tail -n 100 storage/logs/laravel.log
```

To collect application logs through `docker compose logs`, set `LOG_CHANNEL=stderr` in `.env` and rerun the startup command to recreate the containers. The default log file has no persistent volume; configure log collection and retention for your environment.

## Configuration and networking

### HTTPS and reverse proxies

The container's Nginx serves HTTP. In production, configure certificates, HTTP-to-HTTPS redirects, and HSTS at an outer reverse proxy, then forward traffic to the published port. Preserve the original `Host` and set `X-Forwarded-For` and `X-Forwarded-Proto` correctly. The default port mapping listens on all host interfaces; restrict direct access at your network boundary.

The application has no predefined trusted proxy list. When running behind a proxy, configure the trusted proxy addresses actually visible to the application in the `withMiddleware` callback in [`bootstrap/app.php`](../bootstrap/app.php), for example:

```php
$middleware->trustProxies(at: ['10.0.0.10']);
```

Replace the example IP and deploy the configuration using the source-build procedure below. Do not trust forwarding headers from arbitrary clients; setting `APP_URL` does not establish proxy trust. For a manual deployment where Nginx terminates TLS directly, use the example at the end of this guide.

### CSP

The application sets its Content Security Policy through [`SecurityHeaders`](../app/Http/Middleware/SecurityHeaders.php). Its `script-src` retains `'unsafe-inline'` and `'unsafe-eval'` for the public site's inline bootstrap scripts and the admin panel's Alpine.js. An additional CSP at the proxy is enforced alongside the application policy; applying a stricter policy directly can block page scripts.

Before removing these allowances, add matching per-request nonces to inline scripts and the response policy, switch to Alpine's CSP build, and adapt the relevant expressions. Then verify the public pages, admin forms, and navigation.

### Uploads, cache, and mail

- **Uploads**: carousel images are limited to 20 MiB. The containers use Nginx `client_max_body_size 22m` and PHP `upload_max_filesize=24M`, `post_max_size=24M`, and `memory_limit=256M`. Allow the corresponding request size at any outer proxy. When changing limits, check the proxy, PHP, and application validation together.
- **File storage**: uploads use the `public` disk at `storage/app/public/`. This directory must be persistent and writable; changing `FILESYSTEM_DISK` alone does not move carousel uploads to object storage. Serve `/storage/` as static files and prohibit script execution.
- **Shared state**: the defaults are `CACHE_STORE=database` and `SESSION_DRIVER=database`. Migrations create the required tables. All application processes must share the cache and lock tables. Any replacement cache backend must support shared atomic locks; an in-process `array` cache cannot provide this.
- **Mail**: the default `MAIL_MAILER=log` does not deliver messages. Configure and test a real SMTP service, including the sender address, before enabling registration email verification in the admin site settings. Verification messages are sent synchronously and do not require a queue worker. Recreate containers after mail configuration changes to refresh the cached configuration.
- **Search**: `CATALOG_SEARCH_DRIVER=like` is the default. On MySQL, you can switch to `fulltext` after the ngram index migration has run; this setting is not a portable full-text search configuration for other databases.

## Updates, backups, and rollback

Before each release, back up the database, uploads, and `.env`, and record the current image versions. Use MySQL backup tools or a consistent snapshot. Keep related data consistent across the backup and verify restoration in a separate environment.

| Data                  | Docker persistence                                       |
| --------------------- | -------------------------------------------------------- |
| MySQL data            | `db-data` named volume                                   |
| Uploaded files        | `uploads` named volume, mounted at `storage/app/public/` |
| Configuration and key | Host `.env` file, backed up securely and separately      |

Keep the same Compose project name to avoid attaching to new volumes after moving directories. Recreating containers preserves named volumes; `docker compose down -v` deletes them and their data. An existing MySQL volume does not update database account passwords when `.env` changes; rotate the database credentials and application configuration together.

The default `runtime` and `web` tags change with releases. For production, pin both image variables in `.env` to the same published version. The version below is an example only:

```dotenv
DOCKER_APP_IMAGE=generalpeople/smartphone-catalog:runtime-v1.0.0
DOCKER_WEB_IMAGE=generalpeople/smartphone-catalog:web-v1.0.0
```

Review the new version's configuration and migration requirements, then run:

```sh
docker compose -f compose.deploy.yml up -d --pull always --wait
```

Use this command after `.env` changes as well; restarting an existing container does not load new environment variables. Application startup rebuilds Laravel's configuration, route, and view caches.

To roll back, select a previous matching `runtime` / `web` image pair and first confirm that the old code can use the current database. Rolling back images does not undo migrations. Incompatible schema or data changes may require restoring the corresponding database backup. Do not treat `migrate:rollback` as a general recovery procedure or regenerate `APP_KEY`.

Image publishing is configured in the [GitHub Actions workflow](../.github/workflows/publish-images.yml). Repository maintainers must set `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN`; deploying the public images does not require those publishing credentials.

## Build images from source

To change application code or container configuration, use [`compose.yml`](../compose.yml) in a fresh clone. The environment and backup requirements above still apply:

```sh
cp .env.docker.example .env
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show
```

Set `APP_KEY`, the public address, database passwords, and the cookie configuration, then run:

```sh
docker compose run --rm migrate
docker compose up -d --wait
```

After registering through the site, initialize the owner:

```sh
docker compose exec app php artisan user:promote owner@example.com --role=owner
```

The source-build migration service belongs to the `tools` profile and must be run explicitly. For later updates, back up data, obtain the new source, build, migrate, and start the services in that order. The build installs PHP dependencies and generates both asset bundles. Rebuild images for code or configuration changes; editing a running container is not a release process.

## Manual deployment

The server needs PHP 8.5, Composer 2, PHP-FPM, Nginx, and SQLite or MySQL. Enable the extensions required by Composer dependencies, the appropriate PDO driver, `fileinfo`, and `gd` with JPEG, PNG, WebP, and GIF support. Built assets are committed, so Node.js is not needed at runtime. To rebuild them, install the build dependencies described in the [development guide](DEVELOPMENT.en.md).

### Install and release

```sh
git clone https://github.com/GeneralPeople1970/smartphone-catalog.git
cd smartphone-catalog
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Apply the production configuration described above. For MySQL, create a database and an account with permission to run migrations, then set `DB_CONNECTION=mysql` and the `DB_*` values. For the default SQLite connection, create the database file first:

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

The web process needs write access to `storage/` and `bootstrap/cache/`. SQLite also requires write access to its database file and directory. Persist the upload directory, and retain `.env`, the database, and uploads across releases.

```sh
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point the Nginx document root at the project's `public/` directory and serve requests through PHP-FPM. Register at `/register`, then run:

```sh
php artisan user:promote owner@example.com --role=owner
```

For subsequent releases, retain the key and data, install the locked Composer dependencies, run migrations, refresh the caches above, and reload PHP-FPM using your server's service configuration. The same upload, shared-cache, mail, and backup requirements apply as with Docker.

### Nginx example

This example terminates TLS directly in Nginx. Replace the domain, certificate paths, project path, and PHP-FPM socket, then validate the Nginx configuration before reloading it. Built assets receive long-lived cache headers, page routes go through Laravel, and only the `index.php` front controller can execute.

```nginx
server {
    listen 443 ssl;
    server_name example.com;
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    root /var/www/smartphone-catalog/public;
    index index.php;
    client_max_body_size 22m;
    error_page 404 /404.html;

    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000" always;

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* ^/storage/.*\.(php[0-9]?|pht|phtml|phps|phar|pl|py|cgi|sh|shtml)(/|$) {
        deny all;
    }

    location ~* ^/(build|frontend)/assets/.*\.(css|js|mjs|woff2?)$ {
        try_files $uri =404;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Strict-Transport-Security "max-age=31536000" always;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ {
        return 404;
    }
}

server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}
```

Docker uses its own [`docker/nginx/default.conf`](../docker/nginx/default.conf), which serves uploads directly from the shared volume. Do not apply the host paths in this example to that container configuration.
