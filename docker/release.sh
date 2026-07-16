#!/bin/sh
# One-shot release task: run database migrations, then exit. Wired as the
# dedicated `migrate` service in compose.yml so exactly ONE container performs
# migrations even when the app service is scaled to N replicas.
#
# NOTE: runs at release time, never during image build — it needs the injected
# runtime environment (APP_KEY, DB_*), which is deliberately not baked into the
# image.
set -e

echo "[migrate] waiting for database ..."
# Wait until the DB accepts our credentials (compose depends_on only waits for
# the container/healthcheck, not necessarily for credentials to be ready).
tries=0
until php -r '
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s",
                getenv("DB_HOST") ?: "db",
                getenv("DB_PORT") ?: "3306",
                getenv("DB_DATABASE") ?: "laravel"),
            getenv("DB_USERNAME") ?: "laravel",
            getenv("DB_PASSWORD") ?: ""
        );
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
'; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "[migrate] database not reachable after 60 attempts, aborting." >&2
        exit 1
    fi
    sleep 2
done

echo "[migrate] running migrations ..."
php artisan migrate --force

echo "[migrate] done."
