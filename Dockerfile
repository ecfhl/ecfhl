FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev libxml2-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql zip dom gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.json
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-dev
RUN php tests/drafts-only.php
RUN php tests/owner-accounts.php

CMD ["sh", "-c", "php artisan migrate --force || exit 1; (php artisan ecfhl:refresh-pp-lines || true) & php artisan schedule:work & exec php artisan serve --host=0.0.0.0 --port=$PORT"]
