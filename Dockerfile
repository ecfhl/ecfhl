FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx unzip libzip-dev libxml2-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql zip dom gd opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.json
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-dev
RUN php tests/drafts-only.php
RUN php tests/owner-accounts.php
RUN php tests/performance.php
RUN php tests/site-audit.php
RUN php tests/live-scoring.php
RUN php tests/standings.php
RUN php tests/standings-collector.php
RUN php tests/player-projections.php
RUN php tests/season-players.php
RUN php tests/odds.php
RUN php tests/home.php
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-ecfhl.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ecfhl.ini

CMD ["bash", "/app/docker/start.sh"]
