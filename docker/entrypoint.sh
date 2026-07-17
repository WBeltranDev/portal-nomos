#!/usr/bin/env sh
set -e

if [ ! -d /var/www/html/vendor ] && [ -f /var/www/html/composer.json ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
  cp /var/www/html/.env.example /var/www/html/.env
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

exec "$@"
