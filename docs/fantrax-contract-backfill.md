# Fantrax historical contract backfill

## Goal

Populate `contract_years_at_trade` for every **player** asset in the ECFHL trade archive using the historical Fantrax league for the season in which the trade occurred.

Do not infer or guess a contract. Only record a value actually shown by Fantrax.

## Source data

The ECFHL archive source is `database/data/ecfhl_database.txt`.

The `seasons` table contains `season_id`, `season_name`, and `league_id`. Use the `league_id` belonging to the trade's season.

The `trades` / `trade_assets` data identifies each traded player. Only assets with `asset_type = player` require a Fantrax contract lookup. Draft picks do not.

Yahoo seasons without a Fantrax `league_id` cannot be looked up using this method and should remain null unless a verified source is available.

## Fantrax lookup method

For every traded player, construct this URL:

```text
https://www.fantrax.com/fantasy/league/{LEAGUE_ID}/players;statusOrTeamFilter=ALL;pageNumber=1;searchName={URL_ENCODED_PLAYER_NAME};miscDisplayType=1;positionOrGroup=ALL
```

Example for Connor McDavid in league `vxqmljf1ma1ct9af`:

```text
https://www.fantrax.com/fantasy/league/vxqmljf1ma1ct9af/players;statusOrTeamFilter=ALL;pageNumber=1;searchName=Connor%20McDavid;miscDisplayType=1;positionOrGroup=ALL
```

Open the page and read the player's value in the **Contract** column. A browser-agent test on 2026-09-25 confirmed that the example above displays `3 YEAR` for Connor McDavid.

## Important rules

1. Use the Fantrax league ID for the **season of the trade**, not the current league.
2. Match the correct player. Do not use a similarly named search result.
3. Record the numeric contract length in `contract_years_at_trade` (for example, `3 YEAR` becomes `3`).
4. If Fantrax displays no contract, the player cannot be found, the result is ambiguous, or the historical page is inaccessible, leave the value null and record the lookup as unresolved rather than guessing.
5. The same player may have different contract lengths in different seasons. Treat each season/player combination independently.
6. If the same player appears in multiple trades during the same season, verify whether Fantrax's historical season page represents the required contract value before reusing it. Do not assume values across seasons.
7. Do not alter draft-pick assets.

## Preferred browser workflow

Process the archive season-by-season to minimize navigation:

1. Read all player assets for one season.
2. Deduplicate player names within that season for lookup purposes.
3. Open that season's Fantrax player page.
4. Search each player name and read the Contract column.
5. Keep a season/player -> contract mapping.
6. Apply the verified value to the applicable `trade_assets.contract_years_at_trade` records.
7. Continue with the next season.

The archive currently contains roughly 700 traded-player rows, so this should be treated as a long-running browser/data-entry task rather than hundreds of independent browser jobs.

## Website display

The ECFHL application already supports `contract_years_at_trade`. Preserve the existing display behavior so a populated player is shown with its contract in parentheses, e.g.:

```text
Kyle Connor (2 Years)
```

Use `1 Year` for singular and `N Years` for plural if display formatting needs to be touched.

## Completion checks

Before committing:

- Count all player trade assets.
- Count player trade assets with a non-null `contract_years_at_trade`.
- List unresolved player assets grouped by season and reason.
- Spot-check several values directly against Fantrax, including multiple seasons.
- Confirm draft picks were not modified.
- Confirm the Trades page renders the contract after player names.

Commit the completed data changes to the default branch of `ecfhl/ecfhl`. Railway is connected to the repository and should deploy the resulting commit automatically.