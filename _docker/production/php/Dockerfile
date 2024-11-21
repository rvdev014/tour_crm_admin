FROM php:8.2-fpm

COPY ./_docker/production/php/php.ini /usr/local/etc/php/conf.d/php.ini

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

WORKDIR /app

COPY ./composer.json composer.json
COPY ./composer.lock composer.lock

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

COPY . .

RUN chmod -R 777 public
RUN chmod -R 777 storage

EXPOSE 9000
