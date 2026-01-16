#!/bin/sh
set -eu
# enable command tracing for easier debugging in logs
set -x

# default PORT if Cloud Run doesn't set it
: "${PORT:=8080}"

# best-effort: ensure web user owns app files
chown -R www-data:www-data /var/www/html || true

# render nginx config (only PORT)
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

echo "=== Rendered /etc/nginx/nginx.conf ==="
cat /etc/nginx/nginx.conf
echo "======================================"

# test nginx config, fail early with logs if invalid
if ! nginx -t; then
  echo "nginx -t failed, dumping error log (if exists):"
  [ -f /var/log/nginx/error.log ] && tail -n 200 /var/log/nginx/error.log || true
  exit 1
fi

# Start php-fpm in background and capture output
echo "Starting php-fpm..."
php-fpm -F > /var/log/php-fpm-stdout.log 2>&1 &
PHP_FPM_PID=$!

# wait a short moment for php-fpm to start and show logs
sleep 0.8
echo "php-fpm log (last 100 lines):"
tail -n 100 /var/log/php-fpm-stdout.log || true

# Start nginx in foreground (PID 1)
echo "Starting nginx..."
exec nginx -g 'daemon off;'
