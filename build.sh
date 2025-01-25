#!/bin/bash

echo "Running deploy script"

echo "[1/3] Pulling latest changes from git"
git pull

echo "[2/3] Installing dependencies"
composer install --optimize-autoloader --no-dev --no-interaction --no-progress --prefer-dist

echo "[3/3] Run artisan commands"
php artisan migrate --force
php artisan optimize
php artisan icons:cache
