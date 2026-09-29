# Daily Faceoff starting-goalie source

Verified in the Work browser on September 28/29, 2026 (America/Halifax):
`https://www.dailyfaceoff.com/starting-goalies/YYYY-MM-DD` contains
`<script id="__NEXT_DATA__" type="application/json">`.
The matchup cards use `props.pageProps.data`, an array of game objects.
The page identifier is `/starting-goalies/[[...date]]`; `props.pageProps.date`
and every game's `date` identify the requested calendar date.

Each game has `awayGoalieName`, `homeGoalieName`, `awayTeamName`,
`homeTeamName`, `awayNewsStrengthName`, `homeNewsStrengthName`, and
`awayNewsCreatedAt` / `homeNewsCreatedAt`. Opponent and HOME/AWAY follow
from the two sides of the same game. The page displays a null strength as
Unconfirmed. Confirmed and Probable are explicit strength names.

On September 29 the page showed five matchups / ten goalies, including
Tristan Jarry (EDM, HOME vs VAN, Confirmed) and Kevin Lankinen (VAN, AWAY
at EDM, Unconfirmed). September 30 showed three matchups / six goalies,
all Unconfirmed. September 28 returned a valid empty data array.
The reduced fixtures in tests/fixtures retain only the observed fields
used by the collector. They are test data, never a production fallback.

The collector now decodes this JSON using DOMDocument and json_decode.
It does not scrape rendered prose, use Jina, hard-code a Next.js build ID,
or require a browser on Railway. Unknown/missing fields, mismatched dates,
HTTP failures, or incomplete games fail before writes. An explicit empty
array is accepted only within the validated date-specific page payload.
Each successfully parsed date is replaced in a transaction. The command
attempts both dates in America/Halifax and exits nonzero if either fails.
Completion logs include the records read back from active_starting_goalies.

AI Tips joins by date, normalized player name, and NHL team. Current DFO
status and matchup override the available-goalie snapshot. Other available
goalies on a confirmed starter's team remain visible, with a greyed row and
a disabled, non-link Add control.

Run `php tests/starting-goalies.php` and `php tests/ai-tips.php` for isolated
SQLite checks. Run `php artisan ecfhl:refresh-starting-goalies` in production
(or the existing Goalies Run Now action on Job Status) for a live refresh.
