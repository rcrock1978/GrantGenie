# Backend (Laravel/PHP)
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libssl-dev librabbitmq-dev \
        libonig-dev libxml2-dev libcurl4-openssl-dev libmemcached-dev zlib1g-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-dev || composer install --no-interaction --no-scripts

COPY . .

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
