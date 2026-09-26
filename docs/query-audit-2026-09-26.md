# Query audit — September 26, 2026

Reviewed the runtime query paths in `routes/web.php`, `Archive`, inherited `EcfhlData` methods, Blade queries, and their consumers for Overview, Seasons, season detail, Franchises, franchise detail, Trades, Draft, Prizes, Players, and Rules. Also inspected schema, import, and migration queries. No production data was reseeded or rewritten by this change.

## Corrected

- Player history returned `type/detail` while the page required `kind/data`. It now renders the expected fields, orders events oldest first, and emits one event per matching trade.
- Rules referenced nonexistent `rule_title` and returned nested objects where the view expected section-to-text lists.
- Fees ignored nearly every season because the per-franchise fee field was blank. Use recorded season cash fees divided among distinct members when that field is absent. Keep the 2020-21 $15 cash fee and $490 league credit separate from winnings.
- Franchise season counts omitted 2008-09 because its standings are missing. Count distinct memberships, including standings-only records.
- Drafts used all-or-nothing cache fallback and discarded recorded franchise IDs. Merge cache and relational picks by season/overall pick, with relational records taking precedence. Resolve cached names in the relevant season and retain unresolved ambiguity.
- Franchise draft links filtered current names against historical names. Use franchise IDs; preserve the filter when changing seasons. Recognize legacy franchise trade links.
- Franchise trade counts now honor the selected season types and exclude vetoed trades, matching the completed-trade leaderboards. The archive list and its archive count still include labelled vetoed proposals.
- Season awards use that season's team names. The Most Awards leaderboard includes President and Fpts Leader awards, which the player-only event query omitted.
- Do not rank missing Fpts or unplayed records, or show unranked upcoming teams as a regular-season top three.
- The overview President's Trophy label uses the actual recorded award, and does not label a Total Points winner as President.
- Season earnings use one shared aggregation, avoiding duplicate summing logic in routes and views.
- Award ordering uses portable SQL CASE expressions with the same ordering as MySQL FIELD.
- Alternate stored pick descriptions such as `2027 Round 3 Pick` link to drafts instead of player searches.
- Cached trade deduplication includes season as well as source trade ID.
- The seeder rejects a clipped historical export before truncating any tables; a regression check verifies that rejection leaves existing rows intact.

## Verification

`php tests/archive-smoke.php` uses disposable SQLite only. It creates schema, imports the checked-in historical fixture, then applies subsequent migrations in production order. It checks all main pages under h2h/total/all/none, all recorded season and franchise detail pages, Blade compilation, and verified contract rendering. `tests/query-audit.php` adds independent reconciliation and targeted regression cases.

Recorded totals in the fixture:

| Selection | Prizes | Cash fees |
| --- | ---: | ---: |
| Head-to-head | $4,410 | $4,410 |
| Total Points | $6,500 | $6,500 |
| Both | $10,910 | $10,910 |
| Neither | $0 | $0 |

## Limits and separate data issues

This verifies query behavior against stored records; it does not independently re-scrape every historical Fantrax result. The checked-in CSV export has only 1,000 draft picks and 1,000 trade assets, whereas the seeder expects 2,103 and 1,881. Runtime cache supplements some records. The seeder now refuses this incomplete export before any writes. The legacy `ecfhl:sync` URLs point to JSON endpoints on the replaced site; this command is not part of deployment and was not run. Restoring missing source records and replacing the obsolete sync source require a separate data recovery task.

Historical rules in the fixture may differ from later live edits (the fixture still says no IR slots). This audit preserves stored rule text. Contract provenance and historical source corrections require separate evidence; no new contracts were inferred during the audit.
