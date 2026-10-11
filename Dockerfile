# syntax=docker/dockerfile:1
# ps-backend (Laravel 12, PHP 8.3) for Dokploy. One image, three roles:
# web (default), queue worker, scheduler — see docker-compose.yml.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Platform requirements (gd, zip, ...) are satisfied by the runtime image below.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM serversideup/php:8.3-fpm-nginx AS runtime
USER root
# dompdf + endroid/qr-code need gd; maatwebsite/excel needs zip; the rest are Laravel basics.
RUN install-php-extensions gd zip bcmath intl exif pdo_mysql
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY docker/entrypoint.d/ /etc/entrypoint.d/
RUN chmod +x /etc/entrypoint.d/*.sh
USER www-data
