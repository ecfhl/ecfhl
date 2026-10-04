#!/bin/bash
set -euo pipefail
cd /app
[[ "${PORT:-8080}" =~ ^[0-9]+$ ]] || exit 1
sed "s/__PORT__/${PORT:-8080}/g" /app/docker/nginx.conf > /tmp/ecfhl-nginx.conf
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs public/media/team-icons bootstrap/cache
php artisan migrate --force
php artisan config:cache
php artisan view:cache
# Prebuild originals and thumbnails before accepting page requests.
php artisan ecfhl:warm-images || echo 'Image warmup incomplete; missing images will be generated on first use.' >&2
chown -R www-data:www-data storage bootstrap/cache public/media/team-icons
nginx -t -c /tmp/ecfhl-nginx.conf
php-fpm -F &
php_pid=$!
# Keep collector-created cache files writable by PHP-FPM as they expire.
su -s /bin/sh www-data -c 'exec php artisan schedule:work' &
scheduler_pid=$!
nginx -c /tmp/ecfhl-nginx.conf -g 'daemon off;' &
web_pid=$!
cleanup() { kill -TERM "$web_pid" "$php_pid" "$scheduler_pid" 2>/dev/null || true; wait || true; }
trap cleanup EXIT
trap 'exit 0' TERM INT
# Exit if a core process dies so Railway can restart a genuinely failed container.
wait -n "$php_pid" "$web_pid" "$scheduler_pid"
