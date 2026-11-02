# Collector controls and Fantrax transaction checks

Administrators can change each collector from **Collectors → Schedule settings**. Frequencies, daily/weekly Atlantic-time schedules, live-game/idle intervals, and transaction triggers are stored in `collector_schedules`. Changes take effect on the next scheduler tick without restarting the service. **Off** pauses automatic and transaction-triggered runs; **Run Now** remains available. Refresh Data retains its original eight data collectors and does not run maintenance.

| Collector | Default |
| --- | --- |
| Fantrax Transactions | Every 5 minutes |
| Fantasy Team Rosters | After claim/drop, trade, or lineup changes; hourly backup |
| Fantrax Available Players | After claim/drop or trade changes; hourly backup |
| Fantrax Live Scoring | Every minute during games; hourly idle |
| Current Standings | Every minute during games; hourly idle |
| Daily Faceoff Goalies | Every 5 minutes |
| Daily Faceoff Lines | Hourly |
| NHL Odds | Every 2 hours |
| Projected FPts | Daily, 04:00 Atlantic |
| Player Birthdates | Daily, 03:50 Atlantic |
| Scoring Period Matchups | Monday, 08:00 Atlantic |
| Available Goalie Projections | Hourly at :35; also after player refreshes unless paused |
| Database Maintenance | Daily, 04:30 Atlantic |

The transaction watcher polls the public data behind the Fantrax Transaction History page; this is not a webhook. One batched `getTransactionDetailsHistory` request reads the latest Claim/Drop, Trade, and Lineup pages with executed-only filters. Fingerprints contain transaction identifiers, player identifiers, execution state, and transaction cells, excluding changing player news and injury icons. A changed latest page triggers a complete current dataset refresh, so a burst exceeding one page does not require replaying every event.

Per-target fingerprints are acknowledged only after a successful refresh. Failures, malformed feeds, and overlapping runs leave that target pending for the next poll. Successful targets are not repeated when another target fails. The first valid check and each Pacific fantasy-date rollover refresh dated datasets. Periodic backups handle source corrections and changes absent from transaction history; those backups remain active when the transaction source fails.

All scheduled collectors run through `ecfhl:collect JOB`. The shared runner and web Run Now actions use a per-collector process lock and record outcomes in `collector_job_statuses`. Laravel scheduler mutexes provide additional overlap protection. Existing scoring computations and fantasy-day definitions remain in their original collectors. Projected FPts and birthdate jobs force a refresh when run through the controls, so selecting a shorter interval performs an actual refresh rather than a once-daily no-op.

Lineup Advisor has been retired: no page, administration links/routes, recommendation command, scheduler entry, manual collector action, or chained roster/projection rebuild remains. Existing advisor database rows are retained; no destructive migration is needed. Roster counts and Moves remain in the roster section.

Validation: `php tests/collectors.php`, `php tests/standings-collector.php`, and the existing PHP/frontend suites. The transaction fixture is a minimized public response from the league history endpoint.
