# Targeted draft recovery

The original ECFHL_Database.xlsx (September 24, 2026) contains 2,103 picks in 16 drafts. The text export had been clipped at DP01000. Only its drafts and draft_picks sections were restored from the original workbook; other sections were preserved byte for byte.

Run `php artisan ecfhl:validate-drafts` for read-only validation against the current database. Then explicitly run `php artisan db:seed --class=DraftsOnlySeeder --force`.

Do not run the default DatabaseSeeder for this repair. The targeted seeder validates all source records, players, seasons and seasonal franchise mappings before deleting anything. It replaces only drafts and draft_picks in one transaction, with rollback on failure, and verifies fingerprints of other archive tables are unchanged.

Exported franchise IDs are never copied directly to production. Names are matched to the live season's team_seasons. Canonical export names use their original same-season team name from the source team_seasons section to find the corresponding live row. Missing or ambiguous matches stop the operation.

The Docker build runs tests/drafts-only.php using disposable in-memory SQLite. It tests all 16 season pages and franchise filters, repeat execution, transaction rollback and refusal of missing mappings.

The recovered archive has no drafts for 2007-08, 2008-09 or 2010-11, and does not include 2026-27. These are source gaps, not records to fabricate.

If temporarily running the seeder through Railway's pre-deploy command, restore the normal `sh scripts/predeploy.sh` command after successful recovery so later deployments do not reseed drafts.
