# syntax=docker/dockerfile:1

FROM node:22-bookworm-slim AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY . .
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php \
    && composer dump-autoload --optimize \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan package:discover --ansi --no-interaction

FROM php:8.4-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        zip \
        gd \
        opcache \
        pcntl \
        bcmath \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-helpdesk.ini
COPY docker/entrypoint.sh /usr/local/bin/helpdesk-entrypoint
RUN chmod +x /usr/local/bin/helpdesk-entrypoint

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/helpdesk-entrypoint"]
CMD ["php-fpm"]

FROM node:22-bookworm-slim AS realtime

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

COPY realtime ./realtime

ENV REALTIME_WS_HOST=0.0.0.0
ENV REALTIME_WS_PORT=8080

EXPOSE 8080

CMD ["node", "realtime/server.mjs"]

FROM app AS app-dev

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-helpdesk.ini

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
