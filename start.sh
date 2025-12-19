#!/bin/sh
set -e

# default PORT if Cloud Run doesn't set it
: "${PORT:=8080}"

# best-effort: ensure web user owns app files
chown -R www-data:www-data /var/www/html || true

# substitute PORT into nginx config (only $PORT substitution)
envsubst '$PORT' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# optional: warm caches (uncomment if you want)
# php /var/www/html/artisan config:cache || true
# php /var/www/html/artisan route:cache || true

# start php-fpm (nodaemonize / foreground for -F)
php-fpm -F &

# run nginx in foreground so PID 1 is nginx (keeps container alive)
nginx -g 'daemon off;'
