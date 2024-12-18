build:
	docker --log-level debug build --file _docker/production/php/Dockerfile --tag ravshan014/tour-crm-php:1 .
	docker --log-level debug build --file _docker/production/nginx/Dockerfile --tag ravshan014/tour-crm-nginx:1 .

build-%:
	docker --log-level debug build --file _docker/production/php/Dockerfile_$* --tag ravshan014/tour-crm-php:$* .

php-push-%:
	docker push ravshan014/tour-crm-php:$*

nginx-push-%:
	docker push ravshan014/tour-crm-nginx:$*


run:
	docker run -d --name tour-crm-php -p 9000:9000 -it ravshan014/tour-crm-php:1
	docker run -d --name tour-crm-nginx -p 8001:80 -it ravshan014/tour-crm-nginx:1



###### DOCKER-COMPOSE ######

compose-up-prod:
	docker-compose -f docker-compose-prod.yml up -d

compose-down-prod:
	docker-compose -f docker-compose-prod.yml down --remove-orphans

compose-restart-prod: compose-down-prod compose-up-prod

artisan-%:
	docker-compose -f docker-compose-prod.yml exec php php artisan $*
