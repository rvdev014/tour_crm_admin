FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    libpq-dev \
    libc-client-dev \
    libicu-dev \
    zlib1g-dev \
    libpng-dev \
    libzip-dev \
    libkrb5-dev \
    zip && \
    rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
    docker-php-ext-install pgsql pdo_pgsql && \
    docker-php-ext-configure intl && \
    docker-php-ext-install -j$(nproc) intl && \
    docker-php-ext-install zip && \
    docker-php-ext-install gd

COPY --from=composer:2.4.4 /usr/bin/composer /usr/local/bin/composer

RUN adduser nonroot

USER nonroot

WORKDIR /app

COPY ./composer.* .

RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY ./_docker/production/php/php.ini /usr/local/etc/php/conf.d/php.ini

COPY --chown=nonroot:nonroot . .

RUN composer dump-autoload --optimize

EXPOSE 9000
