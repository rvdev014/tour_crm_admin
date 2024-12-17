FROM php:8.2-fpm AS builder

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

################################
FROM php:8.2-fpm-alpine

COPY --from=builder /usr/local/etc/php /usr/local/etc/php
COPY --from=builder /usr/local/lib/php /usr/local/lib/php

RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

RUN addgroup -S appgroup && adduser -S nonroot -G appgroup

USER nonroot

WORKDIR /app

COPY ./composer.json ./composer.lock ./

# Install only production dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction && \
    composer clear-cache

COPY ./_docker/production/php/php.ini /usr/local/etc/php/conf.d/php.ini

COPY --chown=nonroot:nonroot . .

# Generate optimized autoload files
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

EXPOSE 9000
