#!/bin/bash

echo "Running deploy script"

echo "[1/2] Installing dependencies"
composer install --optimize-autoloader --no-dev --no-interaction --no-progress --prefer-dist

echo "[2/2] Run artisan commands"
php artisan migrate --force
php artisan optimize
php artisan icons:cache
