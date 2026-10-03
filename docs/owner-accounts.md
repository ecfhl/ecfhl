# League owner accounts

Public league pages remain available without login. `/register` offers unclaimed current Fantrax teams with their existing uploaded logos. Each owner has one team; the database enforces both exclusive team ownership and one claim per account.

Lone Tsar is reserved using franchise F012 and its current displayed name. Claiming a name never grants administrator access. The bootstrap administrator uses `/account/admin-invite?token=...`, where `ECFHL_ADMIN_INVITE_TOKEN` is a securely generated secret and `ECFHL_ADMIN_INVITE_EXPIRES` is an ISO timestamp. The invitation allows the reserved team for 30 minutes and stops working as soon as an administrator exists. Share this link only with the designated administrator. No bootstrap password is stored in source code.

## Deployment

Migrations add users, exclusive team claims, durable sessions, and per-browser push delivery records. Set `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, and `APP_URL=https://ecfhl.win`. Existing scheduled collectors continue to run directly through Artisan. All web admin pages, collector triggers, advisor mutations, and logo uploads require an authenticated administrator; requests also require CSRF protection.

## Google sign-in

Create a Web application OAuth client in Google Cloud. Configure the consent screen for league users and add this exact authorized redirect URI:

`https://ecfhl.win/auth/google/callback`

Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` on the web service. Google buttons remain hidden while credentials are absent. References: https://developers.google.com/identity/protocols/oauth2/web-server and https://developers.google.com/identity/openid-connect/openid-connect.

Existing password owners connect Google from `/account` after signing in. Their Google email must match their owner email. Email matches alone never link an existing account. Google-only owners can set a password in Account to enable both sign-in methods. OAuth uses a short-lived, single-use session state and server-side authorization-code exchange, with verified user info and Google subject IDs.

## Notifications

`/notifications` saves owner preferences for own scoring, current-opponent scoring, all goalie status changes, available goalies today/tomorrow, and specific available goalie watches. Available goalies must have a dated game and be free agents or clear waivers by that fantasy date. Today/tomorrow use the existing Pacific fantasy-day rules.

Owners explicitly enable each browser/device. Preferences apply across their devices. Existing anonymous subscriptions must be re-enabled under an account. A random per-device feed token is stored in the service worker's IndexedDB. Events are matched to subscriptions server-side; the service worker only reads its own feed, even with a closed browser tab or expired login session. Signing out revokes this browser feed, and unsubscribing removes its deliveries. Changing preferences clears previously queued events.

Score increases for ACTIVE players come from the valid current fantasy-day snapshot. Bench/minor players, first snapshots, decreases, and other dates do not generate score alerts. Goalie changes come from the existing daily goalie collector, including status changes beyond Confirmed/Likely; initial data does not generate a notification storm.

## Verification

`php tests/owner-accounts.php` exercises signup/login, reserved administrator setup, duplicate claims with transaction rollback, guest/member access restrictions, Google state and explicit linking, notification filters, game-date/waiver handling, per-device feed isolation, and browser push endpoint restrictions against SSRF. It uses disposable SQLite and mocked HTTP.

`php tests/live-scoring.php` covers the existing scoring and date regressions. `php artisan view:cache` verifies every Blade template. Browser notification delivery and a real Google sign-in require enabled browser permissions and Google credentials; unit tests use mock services.
