# Performance changes (October 2026)

The web container uses Nginx and three on-demand PHP-FPM workers, rather than the single-worker PHP development server. OPcache is enabled; PHP workers recycle after 500 requests. PHP uses a 192 MB per-process memory limit. Nginx serves static files directly and compresses text responses. The existing scheduler runs alongside the web server.

## Images

Database originals remain authoritative. `php artisan ecfhl:warm-images` rebuilds originals and real 64, 160 and 640 pixel WebP thumbnails under `public/media/team-icons` before accepting traffic. The image warmer reads one original at a time and uses a separate 256 MB CLI limit. Uploads regenerate those files and atomically publish a new manifest. Content hashes in URLs allow immutable browser caching, while image viewers retain access to the original. Missing images can be generated on demand through public routes without session middleware. Generated files need no persistent volume and are rebuilt on deployment.

## Data and SQL

Removed the database counts from application boot. Archive data is cached for five minutes; current team standings for 30 seconds and team menu for 60 seconds. Public scoring snapshots are cached for ten seconds, invalidated when published, and reused within a request. Snapshot reads select the rendered payload and timestamp, excluding the large raw upstream responses. Player projections are cached for 60 seconds and invalidated when refreshed; line and goalie badges for 30 seconds. These caches use the local file store to avoid adding MySQL cache queries. HTML, account state, sessions and notification preferences are not cached.

Archive joins and season awards no longer query once per displayed season. Historical team names are indexed in memory. Immutable contract JSON and league lookup maps are parsed once per request. DATE columns use direct equality predicates, retaining their existing index access. Page rendering does not launch the upstream scoring-period collector. Early-season projection ranges reuse identical fetched totals.

## Verification

`php tests/performance.php` uses a disposable SQLite database and temporary cache/image directory. It checks warm query budgets, actual WebP dimensions and alpha, stateless image requests, ETags, upload cache invalidation, snapshot reuse/publication invalidation and indexed roster lookups. Other checks: `tests/live-scoring.php`, `tests/archive-smoke.php`, `tests/owner-accounts.php`, `tests/ai-tips.php`, `tests/player-projections.php`, and `node tests/owner-preferences.js`.

Before/after warm query counts with the same historical fixture and `type=all`: home 27 to 1; seasons 75 to 0; teams 26 to 0; trades 13 to 0; draft 17 to 0; player search 15 to 0. These are application measurements with array sessions and warmed shared data, excluding network latency and database session reads/writes. Production timing also depends on database/storage health.

Set `PUBLIC_DATA_CACHE=false` and redeploy to bypass public data caches. Cache lifetimes bound staleness between replicas; content files are currently generated on the single production replica. If scaling to multiple replicas, move generated images to shared object storage or regenerate each replica when an upload changes. Code is immutable within a deployment, so OPcache timestamp checks are disabled.
