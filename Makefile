init: stop remove build run

push:
	docker push ravshan014/tour-crm-php:1
	docker push ravshan014/tour-crm-nginx:1

stop:
	docker stop tour-crm-php
	docker stop tour-crm-nginx

remove:
	docker rm tour-crm-php
	docker rm tour-crm-nginx

build:
	docker --log-level debug build --file _docker/production/php/Dockerfile --tag ravshan014/tour-crm-php:1 .
	docker --log-level debug build --file _docker/production/nginx/Dockerfile --tag ravshan014/tour-crm-nginx:1 .

run:
	docker run -d --name tour-crm-php -p 9000:9000 -it ravshan014/tour-crm-php:1
	docker run -d --name tour-crm-nginx -p 8001:80 -it ravshan014/tour-crm-nginx:1
