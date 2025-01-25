#!/bin/bash

echo "Running deploy script"

echo "[1/4] Pulling latest changes from git"
git pull

echo "[2/4] Restarting docker containers"
docker-compose -f docker-compose-prod.yml down --remove-orphans
docker-compose -f docker-compose-prod.yml up -d --build

echo "[3/4] Installing dependencies"
docker-compose exec php composer install --no-interaction --no-progress --no-suggest

echo "[4/4] Run artisan commands"
docker-compose exec php php artisan migrate --force
docker-compose exec php php artisan optimize
docker-compose exec php php artisan icons:cache
