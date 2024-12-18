FROM php:8.2-fpm-alpine

# Install required Alpine packages
RUN apk add --no-cache \
    libpq \
    libsodium \
    unzip \
    postgresql-dev \
    libpng-dev \
    libzip-dev \

# Configure and install PHP extensions
RUN docker-php-ext-configure pgsql && \
    docker-php-ext-configure intl && \
    docker-php-ext-install -j$(nproc) \
        gd \
        pgsql \
        pdo_pgsql \
        intl \
        zip

# Clean up build dependencies
RUN apk del \
    libpng-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libjpeg-dev \
    libzip-dev \
    icu-dev \
    zlib-dev

# Add a non-root user for better security
RUN addgroup -S appgroup && adduser -S nonroot -G appgroup
USER nonroot

WORKDIR /app

EXPOSE 9000

# Default command
CMD ["php-fpm"]
