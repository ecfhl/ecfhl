# Messaging and shared alerts

The header contains messages, notifications, scoring updates, and the theme control. Counters remain visible at zero in gray and highlight above zero. The scoring icon always opens its panel; the checkbox inside separately controls receiving scoring updates. Scoring state, filters, and up to 250 recent batches persist across navigation in the current browser tab, keyed by account. The app polls scoring every minute, independently of Live Scoring page refreshes.

Signed-in users can open `/messages` for the league chat or choose another user for a private conversation. The league chat is also the first section on Home. Team pages and the team-image viewer link to the team's registered owner; the link is absent for your own team and unclaimed teams. Conversation APIs require authentication and expose only the league chat or the requesting user's private conversation with the selected user. Messages are plain text, limited to 4,000 characters. Sending uses a client UUID to make retries safe, and read cursors only move forward within their conversation. Earlier history is available in pages of 50.

Messages and unread counters refresh every 15 seconds while the app is visible. A visible conversation marks messages read when scrolled to the bottom. New incoming messages produce dismissible popups outside their visible conversation. Initial page loads show unread counts without replaying historical messages as new popups. Private and league popup preferences are separate from their push preferences; all default to enabled. Muting push clears pending deliveries of that type, so re-enabling does not replay muted pushes. Closing a message popup does not mark the conversation read.

Browser push reuses the existing account-bound WebPush system. Browsers still require permission; the bell panel provides an Enable browser notifications button. Existing browser permission enables delivery automatically unless the device was explicitly disabled. The bell's status dot reflects the account's master push preference. Bell notifications have their own per-owner inbox, separate from message counts. Opening a notification or marking the displayed notifications read clears its unread count. Message push is sent only to the recipient, or to other signed-in account holders for league chat, respecting their preferences. The sender is excluded.

The migration `2026_10_08_160000_create_messaging_tables.php` adds message history, read cursors, and the notification inbox. It runs through the existing migration/deployment process. No new queue worker or external messaging service is required.

Validation:

```sh
php tests/messaging.php
node tests/live-score-updates.cjs
node tests/live-scoring-ui.cjs
```

An optional browser test uses Playwright with Chromium, an isolated HTML fixture, and mocked APIs. It sends no real messages or push notifications:

```sh
php tests/messaging.php --browser-page
node tests/communication-browser.cjs
```

Set `CHROMIUM_PATH` if the browser executable is installed elsewhere. `ECFHL_BROWSER_SCREENSHOT` optionally saves a mobile screenshot. The browser test checks reopening, navigation persistence, separate receiving control, private message rendering, sending, notification counts, team-owner links, incoming popups, and the 360-pixel layout.
