# syntax=docker/dockerfile:1
#
# Multi-stage build for the smartphone catalog:
#   runtime — php-fpm app image (production vendor + built assets, non-root)
#   web     — nginx image with the built public/ baked in
#
# Orchestrated by compose.yml (app + web + db + one-shot migrate service);
# built and smoke-tested (/up) by the `docker` job in .github/workflows/ci.yml.
# See docs/DEVELOPMENT.md — 容器化部署（Docker）.

# ---- Stage 1: build front-end + admin assets (devDependencies needed) ----
FROM node:22-alpine AS assets
WORKDIR /app

# Install dependencies first for better layer caching. Both lockfiles are needed.
COPY package.json package-lock.json ./
COPY frontend/package.json frontend/package-lock.json ./frontend/
RUN npm ci && npm --prefix frontend ci

# Build admin (-> public/build) and SPA (-> public/frontend).
COPY . .
RUN npm run build

# ---- Stage 2: production PHP dependencies (no dev) ----
FROM php:8.4-cli AS vendor
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
FROM php:8.4-fpm AS runtime
WORKDIR /var/www/html

# System libraries + PHP extensions. gd is required for the carousel image
# re-encode; fileinfo (upload MIME detection) is bundled and enabled by default
# in the official image; swap pdo_mysql for pdo_pgsql/pdo_sqlite as needed.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath opcache \
    && php -m | grep -qi fileinfo \
    && rm -rf /var/lib/apt/lists/*

# Production PHP + OPcache tuning. opcache.validate_timestamps=0 assumes an
# immutable image (code never changes at runtime) — rebuild the image to deploy.
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# App code, then production vendor and freshly built assets from earlier stages.
# .dockerignore keeps .env, vendor, node_modules, test DB and build output out of
# the context, so the assets/vendor copied below are the clean, rebuilt ones.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
COPY --chown=www-data:www-data --from=assets /app/public/frontend ./public/frontend

# Build the package manifest. NOT suppressed with "|| true": a discovery failure
# is a real build error and must fail the image, not be silently swallowed.
RUN php artisan package:discover --ansi

# Release / entrypoint scripts (run at container start, not build time).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/release.sh /usr/local/bin/release.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/release.sh

# storage/ and bootstrap/cache/ must be writable by the fpm worker. Mount a
# persistent volume (or object storage) at storage/app/public for user uploads.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# ---- Stage 4: web (nginx serving the built public/ + proxying php to app) ----
# Bakes the public document root (index.php + built assets) into the nginx image
# so static files are served directly. User uploads come from the shared
# "uploads" volume mounted at /var/www/html/storage/app/public in both images.
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
EXPOSE 80
