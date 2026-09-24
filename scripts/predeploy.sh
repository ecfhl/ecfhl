#!/bin/sh
set -eu

php artisan migrate --force
php artisan db:seed --force
