FROM php:8.2-fpm as builder

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

RUN adduser nonroot


FROM builder as composer

RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

WORKDIR /app

COPY ./composer.json ./composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction


##### PRODUCTION IMAGE PHP ######
FROM php:8.2-fpm-alpine

COPY --from=builder /etc/passwd /etc/passwd
COPY --from=builder /usr/local/etc/php /usr/local/etc/php
COPY --from=builder /usr/local/lib/php /usr/local/lib/php

COPY ./_docker/production/php/php.ini /usr/local/etc/php/conf.d/php.ini

WORKDIR /app

USER nonroot

COPY ./ ./

COPY --from=composer /app/vendor /app/vendor

EXPOSE 9000
