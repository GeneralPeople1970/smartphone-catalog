#!/bin/sh
# App container entrypoint: per-container release steps, then php-fpm.
#
# Framework caches live on each container's own filesystem (bootstrap/cache),
# so they are (re)built here at startup — NOT in the one-shot migrate service,
# whose filesystem the app containers never see.
set -e

echo "[app] linking public/storage ..."
php artisan storage:link --force

echo "[app] caching config/routes/views ..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
