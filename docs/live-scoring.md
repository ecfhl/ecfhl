# Live Scoring pipeline

`php artisan ecfhl:refresh-live-scoring` publishes three separate snapshots, centered
on `FantasyDay::today()`. The sole league calendar boundary is midnight in
`America/Vancouver`. An optional YYYY-MM-DD argument allows diagnostic backfills.
Each date validates and commits independently. A failed date keeps its previously
published payload and collection timestamp; other dates can still succeed.

## Fantrax source investigation (October 3, 2026)

The underlying Fantrax Live Scoring application requests `getLiveScoringStats`
through `POST /fxpa/req?leagueId=092zcn40molvao69`. Its daily view uses:

```
newView: true
date: YYYY-MM-DD
viewType: "1"
playerViewType: "2"
sppId: "-1"
teamId: "ALL"
```

`viewType: "2"` separately supplies the selected scoring period's totals.
The daily response explicitly represents daily rosters, scores, projections and
events. Separate collector classes parse those responsibilities. The roster's
`scorerMap.ACTIVE` and `scorerMap.BENCH` define daily participation, including zero
points. Injury badges and NHL schedules cannot override ACTIVE membership.
Bench/minor participants appear separately and never enter active team totals.

Explicit October 2 requests with both `tz: America/Halifax` and
`tz: America/Vancouver` returned the same date, player membership and fantasy
points. The client retains Halifax for Fantrax requests, independently of the
Pacific league calendar. It verifies both returned date fields, view and provider.

Standard daily GP and additional goalie statistics come from `getPlayerStats`:
`datePlaying`, `startDate` and `endDate` all equal the selected date;
`timeframeTypeCode=BY_DATE`; `scoringCategoryType=1`; groups `HOCKEY_SKATING` and
`POS_201`. Both timezone experiments normalized returned date-picker timestamps
to Eastern midnight. Those timestamps are checked against the requested date.
`datePlaying=ALL` does not reliably supply selected-date opponent information;
neither this response nor next-game projections determines participation.
Pagination is followed. Players missing from ALL_TAKEN get a narrow search, with
the resulting statistics joined strictly by Fantrax player ID.

The projection parser follows Fantrax's application code: player display uses
original projections after an event finishes, otherwise calculated projections
with original as fallback. Team projection sums ACTIVE calculated projections.
Period totals and daily totals come from their respective responses.

## Storage and publication

`live_scoring_snapshots` has a unique league/date key, explicit fantasy/source
dates, source, UTC collection time, validated payload and diagnostic source JSON.
Each payload includes every matchup, team and daily player, keyed by Fantrax IDs.
Writes and job history are transactional. HTTP views select the exact date; they
never fall back to another date or collect source data while serving Live Scoring.
`/api/live-scoring?date=YYYY-MM-DD` exposes the published snapshot and the three
server-resolved calendar dates for verification.

The old `refresh-daily-scores`, old `refresh-live-scoring`, NHL daily-score merger,
and their scheduled writers have been removed. Historical legacy score tables are
retained but are no longer read or written by Live Scoring. The full-roster,
targets, standings, lines, odds and advisor collectors remain for their respective
features. Their roster flags cannot alter Live Scoring snapshots. Optional lines,
PP, odds and starting-goalie badges enrich presentation only.

## Railway scheduling and deployment

The existing web container runs Laravel's scheduler. One authoritative master
command is scheduled every two minutes during Fantrax live events or the expected
game window, and every fifteen minutes otherwise. The scheduler checks every
minute. Overlap prevention and a process lock protect scheduled/manual runs on the
current single Railway replica. A multi-replica deployment would require replacing
the process lock with a shared database/cache lock.

Predeploy migrates the database and runs the master command. All three dates must
validate before the new deployment receives traffic. Job Status reports the new
command, refresh cadence, record count and last successful snapshot time.

## Regression checks

`php tests/live-scoring.php` uses real sanitized October 1/2/3 Fantrax JSON fixtures
in a disposable SQLite database. It checks all seven matchups and fourteen teams
per date, player IDs and points, period/daily totals, daily projections, game
states, zero-point participants, injury-badge independence, failure preservation,
independent publication, and main/My Team page rendering.

The clock tests include October 3 at 00:30 Halifax, immediately before and after
Pacific midnight, and a DST transition. October 2 Lone Tsar contains Carlsson,
Dubois, Eichel and Hintz. Eichel's captured points reflect the game progressing
during source inspection; his identity/presence, rather than an obsolete live
score, is the regression invariant. Tomorrow independently includes thirteen
ACTIVE Lone Tsar participants with positive projections before games start.

The archive smoke checks pass. Existing AI Tips and starting-goalie tests have
pre-existing failures (historical waiver-date expectations and a Probable/Likely
expectation); those were reproduced against the original code.
