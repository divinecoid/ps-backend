#!/bin/sh
# storage/app is a mounted volume (uploads), so on first start it is empty:
# recreate the directories Laravel expects. Safe to run on every start.
mkdir -p /var/www/html/storage/app/public \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache
# Compiled caches depend on the container's env vars; never bake or reuse stale ones.
php /var/www/html/artisan config:clear >/dev/null 2>&1 || true
php /var/www/html/artisan storage:link >/dev/null 2>&1 || true
