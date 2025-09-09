deploy:
	git pull
	composer install --no-dev
	php artisan migrate --force
	php artisan optimize:clear

