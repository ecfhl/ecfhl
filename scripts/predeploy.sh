#!/bin/sh
set -eu

php artisan migrate --force
# Refresh live scoring before switching production traffic, but do not block a
# deployment when Fantrax is temporarily missing a future-date projection.
# The refresh command preserves the previous valid snapshot for failed dates.
if ! php artisan ecfhl:refresh-live-scoring; then
    echo "WARNING: Live scoring refresh failed; previous valid snapshot preserved. Continuing deployment."
fi
# Historical data is already stored in MySQL. Import only as an explicit operation;
# the public domain now serves this app, not the old JSON archive endpoints.

# Repair standings with finalized days before switching production traffic.
if ! php artisan ecfhl:refresh-current-standings; then
    echo 'WARNING: Standings refresh failed; existing standings preserved.'
fi

# Populate complete player stat lines once when this feature is first deployed.
if ! php artisan ecfhl:refresh-player-projections --ensure-season-stats; then
    echo 'WARNING: Season player stats refresh failed; previous valid data preserved.'
fi
