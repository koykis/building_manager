FROM composer:2 AS composer

FROM php:8.4-fpm-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev unzip \
    && docker-php-ext-install -j2 bcmath mbstring pdo_mysql zip opcache \
    && apt-get clean
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY deploy/php-production.ini /usr/local/etc/php/conf.d/oikia.ini
COPY deploy/php-fpm-production.conf /usr/local/etc/php-fpm.d/zz-oikia.conf
