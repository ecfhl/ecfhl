#!/bin/sh
set -eu

php artisan migrate --force
# Validate and seed all three independent dates before switching production traffic.
php artisan ecfhl:refresh-live-scoring
# Historical data is already stored in MySQL. Import only as an explicit operation;
# the public domain now serves this app, not the old JSON archive endpoints.
