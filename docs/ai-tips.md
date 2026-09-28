# AI Tips

`/ai-tips` displays dated, verified pickup snapshots. It is independent of the archive's season-type filter. The date selector uses Atlantic time. No automated scraper or recurring refresh is configured.

Publish an update in `database/data/ai-tips/YYYY-MM-DD.json` using the existing September 29 file as the schema. Record the actual verification timestamp in `checked_at`. Never relabel an older snapshot as a new date.

Read Fantrax's **Sta** column, not **Con**: only FA or W (with the displayed waiver day) qualify. Set the Date Playing filter to the snapshot date. Check all qualifying results before applying limits. For skaters, rank projected season FPts descending; preserve Fantrax rank as the tie breaker. Include up to 10 F and 5 D. Goalies have no cap: match available goalies against Daily Faceoff's listed starters and preserve their exact confirmation status. Do not call an unconfirmed goalie confirmed or treat every backup on a scheduled team as a starter.

Each row has `name`, `team`, `position` (G/F/D), `game_date`, `opponent` (MTL at home, @MTL away), and `status`. Skaters also have `projected_points` and `source_rank`; goalies have `starting_status`. Verify NHL team and opponent directly from source content.

The page explicitly labels snapshot freshness, projected season points, and unconfirmed starts. Missing dates show an empty state and links to published dates; they never reuse another day's player list.

Validation: `php tests/ai-tips.php` checks eligibility, date isolation, ranking, limits, and Blade rendering.
