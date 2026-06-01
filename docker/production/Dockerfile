# syntax=docker/dockerfile:1

FROM php:8.5-cli-bookworm AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libpng-dev \
        libzip-dev \
        libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        intl \
        pdo_pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM node:24-alpine AS assets

WORKDIR /app

ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_APP_NAME=Nerdik

ENV VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY}"
ENV VITE_REVERB_HOST="${VITE_REVERB_HOST}"
ENV VITE_REVERB_PORT="${VITE_REVERB_PORT}"
ENV VITE_REVERB_SCHEME="${VITE_REVERB_SCHEME}"
ENV VITE_APP_NAME="${VITE_APP_NAME}"

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app /app

RUN npm run build

FROM php:8.5-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        gosu \
        nginx \
        supervisor \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        pdo_pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/production/conf.d/ /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

COPY docker/production/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint

RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak \
    && chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    LOG_CHANNEL=stderr

RUN php artisan package:discover --ansi \
    && php artisan filament:upgrade --ansi

ENTRYPOINT ["/usr/local/bin/entrypoint"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
