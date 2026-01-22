deploy:
	git pull
	composer install --no-dev
	php artisan migrate --force
	php artisan optimize:clear

backup:
	ssh tour_crm "PGPASSWORD='qwerty6491' pg_dump -h 127.0.0.1 -U developer -d tour_crm" > backup_1.sql

restore-db:
	docker compose exec -T db psql -U developer -d tour_crm -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
	docker compose exec -T db psql -U developer -d tour_crm < backup_1.sql


#cat backup_2.sql | ssh small "psql -U developer -d wms"