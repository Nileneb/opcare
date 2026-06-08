# syntax=docker/dockerfile:1
#
# Ein einziges Dockerfile, mehrere Targets:
#   app     – PHP-FPM mit gebackenem Code + Vendor + Vite-Assets (Default-Laufzeit)
#   web     – nginx mit denselben public/-Assets (statisches Ausliefern + FastCGI-Proxy)
#   testing – wie app, aber mit dev-Dependencies für die Pest-Suite
#
# `docker compose up --build` baut app+web aus diesem File; der Code ist eingebacken,
# d. h. der Stack läuft ohne Host-Bind-Mounts (nur .env wird gemountet).

# --- Stage 1: Frontend-Assets (Vite + Tailwind v4) -------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* vite.config.js ./
RUN npm ci
COPY resources ./resources
COPY public ./public
# WHY: Vite inlined import.meta.env.VITE_* zur BUILD-Zeit. Ohne diese Build-Args wäre der
# Reverb-Client-Key im Bundle undefined → window.Echo würde nie erzeugt und der Chat fiele
# stumm auf Polling zurück. Die VITE_REVERB_*-Werte sind Client-public (kein Secret).
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

# --- Stage 2: PHP-Basis (Extensions + Composer) ----------------------------------
FROM php:8.4-fpm-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libicu-dev libpq-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd zip intl bcmath pcntl exif opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-opcare.ini
WORKDIR /var/www

# --- Stage 3: App (Code + Vendor + Assets, eingebacken) --------------------------
FROM base AS app
# Vendor zuerst (eigene Layer für besseres Caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    # public/storage zeigt auf den geteilten storage-Volume (zur Laufzeit befüllt)
    && ln -sf /var/www/storage/app/public /var/www/public/storage \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# --- Stage 4: Web (nginx mit denselben public/-Assets) ---------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/public /var/www/public

# --- Stage 5: Testing (dev-Deps, Pest) -------------------------------------------
FROM base AS testing
COPY . .
RUN composer install --prefer-dist --no-interaction --no-progress
ENTRYPOINT []
CMD ["php", "artisan", "test"]
