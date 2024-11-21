FROM nginx:latest

COPY ./_docker/production/nginx/conf.d /etc/nginx/conf.d

WORKDIR /app

COPY ./public public

EXPOSE 80
