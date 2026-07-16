# syntax=docker/dockerfile:1
#
# Multi-stage build for the smartphone catalog. Produces a php-fpm runtime image
# that contains ONLY runtime files: app code, production Composer dependencies
# and the compiled front-end. Pair it with an Nginx container (see
# docs/DEVELOPMENT.md — Nginx 示例) whose root is this image's /var/www/html/public.
#
# This is a starting template: it has not been built inside this repo's CI.
# Verify `docker build .` in your own environment and adjust PHP extensions /
# database driver to match your deployment before relying on it.

# ---- Stage 1: build front-end + admin assets (devDependencies needed) ----
FROM node:24-alpine AS assets
WORKDIR /app

# Install dependencies first for better layer caching. Both lockfiles are needed.
COPY package.json package-lock.json ./
COPY frontend/package.json frontend/package-lock.json ./frontend/
RUN npm ci && npm --prefix frontend ci

# Build admin (-> public/build) and SPA (-> public/frontend).
COPY . .
RUN npm run build

# ---- Stage 2: production PHP dependencies (no dev) ----
FROM php:8.5-cli AS vendor
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --no-scripts avoids booting the app at build time; the optimized autoloader is
# generated after the source is copied. artisan package:discover runs in the
# runtime stage (or lazily on first request).
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---- Stage 3: runtime (php-fpm) ----
FROM php:8.5-fpm AS runtime
WORKDIR /var/www/html

# System libraries + PHP extensions. gd is required for the carousel image
# re-encode; swap pdo_mysql for pdo_pgsql/pdo_sqlite to match your database.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath \
    && rm -rf /var/lib/apt/lists/*

# App code, then production vendor and freshly built assets from earlier stages.
# .dockerignore keeps .env, vendor, node_modules, test DB and build output out of
# the context, so the assets/vendor copied below are the clean, rebuilt ones.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
COPY --chown=www-data:www-data --from=assets /app/public/frontend ./public/frontend

# Build the package manifest now; harmless if it regenerates at runtime.
RUN php artisan package:discover --ansi || true

# storage/ and bootstrap/cache/ must be writable by the fpm worker. Mount a
# persistent volume (or object storage) at storage/app/public for user uploads.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
