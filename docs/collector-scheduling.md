# Collector scheduling

NHL score feeds for yesterday, today and tomorrow determine game windows. Date
selection uses `FantasyDay` (America/Vancouver); puck drops are UTC instants.
Schedules are cached for one minute around games, five minutes outside games,
15 minutes for tomorrow and one hour for completed historical days. An NHL
outage keeps minute scoring enabled and suppresses paid odds requests.

| Collector | Automatic cadence |
| --- | --- |
| Live scoring | Every minute during games; hourly idle; final reconciliation |
| Standings | Every five minutes during games; retry final reconciliation until success |
| Fantrax daily players and rosters | Every 15 minutes during 8 a.m.–10 p.m. Pacific, pregame or live windows; every three hours otherwise |
| DFO goalies | Every five minutes within six hours of an upcoming puck drop; every three hours elsewhere |
| PP lines | Every three hours, Pacific clock |
| Odds | Every four hours, Pacific clock, only while today's schedule has an unstarted game |
| Lineup advisor | Every three hours, plus every 30 minutes within six hours of puck drop |
| Birthdates | Monday 3:50 a.m. Atlantic; locally calculated ages need no daily fetch |
| Projections | Daily 4 a.m. Atlantic |
| Scoring-period matchups | Monday 8 a.m. Atlantic |

Live/critical NHL states preserve scoring through long overtime and Pacific
midnight. A scheduled start also opens the live window for up to five hours if
the live state is delayed. Postponements do not open windows. The first observed
all-final schedule creates a cached reconciliation marker. Successful score and
standings runs after that marker satisfy it; failures retry. Keep the configured
Laravel cache persistent across scheduler invocations.

Roster refreshes freeze the current day's snapshot when all NHL games are final;
tomorrow remains refreshable. An unavailable schedule preserves today's lineup.
Lineup advice runs on its own cadence, rather than after every roster refresh.
Both daily players and DFO goalie collectors rebuild available goalies, including
after partial source updates, and propagate rebuild failures. The redundant
hourly rebuild has been removed; its manual command remains available.

The administrator status page polls `/job-status/state`, using the existing admin
middleware. Scheduled and manual command events share running/result tracking.
Progress counts completed date/team/source-window units, including unchanged
data. Work without a known total uses an indeterminate bar. Full diagnostics are
kept under Details; a worker left running for two hours is shown as interrupted.
All displayed timestamps use America/Halifax.

## Validation and rollout

Run the archive workflow plus `php tests/collector-schedule.php` and
`node tests/collector-status-ui.cjs`. The new migration creates
`collector_run_progress`; it leaves the existing collector status schema intact.
When deployment is approved, run normal migrations before using progress tracking.
This change does not deploy or merge itself.
