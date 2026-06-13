FROM php:8.3-cli-alpine AS base

RUN apk add --no-cache bash curl icu-libs libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS curl-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install bcmath curl intl mbstring pcntl pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS vendor

COPY services/api-gateway/composer.json services/api-gateway/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY services/api-gateway/ ./
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

FROM base AS runtime

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --chown=www-data:www-data docker/laravel/entrypoint.sh /usr/local/bin/laravel-entrypoint

RUN chmod +x /usr/local/bin/laravel-entrypoint \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public

USER www-data

EXPOSE 8000

ENTRYPOINT ["laravel-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
