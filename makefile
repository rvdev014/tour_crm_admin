############# BUILD PUSH CONTAINERS #############

php-build-%:
	docker --log-level debug build --file _docker/production/php/Dockerfile_$* --tag ravshan014/tour-crm-php:$* .

nginx-build:
	docker --log-level debug build --file _docker/production/nginx/Dockerfile --tag ravshan014/tour-crm-nginx:1 .

nginx-build-%:
	docker --log-level debug build --file _docker/production/nginx/Dockerfile_$* --tag ravshan014/tour-crm-nginx:$* .

php-push-%:
	docker push ravshan014/tour-crm-php:$*

nginx-push-%:
	docker push ravshan014/tour-crm-nginx:$*

push:
	make php-push-1
	make nginx-push-1


############# DOCKER COMPOSE #############

compose-restart: compose-down compose-up
compose-restart-prod: compose-down-prod compose-up-prod

compose-up:
	docker-compose up -d
compose-down:
	docker-compose down --remove-orphans

compose-up-prod:
	docker-compose -f docker-compose-prod.yml up -d
compose-down-prod:
	docker-compose -f docker-compose-prod.yml down --remove-orphans


############# APP COMMANDS #############
include .env
export $(shell sed 's/=.*//' .env)

args = $(filter-out $@,$(MAKECMDGOALS))

.PHONY: artisan
artisan:
	docker-compose exec php php artisan $(args)

.PHONY: composer
composer:
	docker-compose exec php composer $(args)

.PHONY: backup
backup:
	docker-compose exec db pg_dumpall -c -U postgres > backups/backup.sql
