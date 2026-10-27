#!/bin/bash
set -euo pipefail
cd /app
[[ "${PORT:-8080}" =~ ^[0-9]+$ ]] || exit 1
sed "s/__PORT__/${PORT:-8080}/g" /app/docker/nginx.conf > /tmp/ecfhl-nginx.conf
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs public/media/team-icons bootstrap/cache

# Complete schema updates before serving traffic, but do not perform expensive
# maintenance, compaction, image warmup, or projection backfills on every restart.
php artisan migrate --force
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache public/media/team-icons
nginx -t -c /tmp/ecfhl-nginx.conf

php-fpm -F &
php_pid=$!
su -s /bin/sh www-data -c 'exec php artisan schedule:work' &
scheduler_pid=$!
nginx -c /tmp/ecfhl-nginx.conf -g 'daemon off;' &
web_pid=$!

cleanup() {
  kill -TERM "$web_pid" "$php_pid" "$scheduler_pid" 2>/dev/null || true
  wait || true
}
trap cleanup EXIT
trap 'exit 0' TERM INT
# Fail if a core process exits so the hosting platform can restart it.
wait -n "$php_pid" "$web_pid" "$scheduler_pid"
