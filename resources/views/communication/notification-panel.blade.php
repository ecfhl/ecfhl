<section id="notification-panel" class="communication-panel" aria-label="Notifications" hidden>
 <div class="communication-panel-heading"><h2>Notifications</h2><button type="button" data-close-notifications aria-label="Close notifications">×</button></div>
 @auth
 <label class="communication-setting"><input type="checkbox" id="header-notifications-enabled" checked> Notifications enabled</label>
 <p id="header-push-state" role="status"></p><button type="button" class="button" id="header-enable-push">Enable browser notifications</button>
 <div id="notification-inbox" class="communication-log"></div>
 <button type="button" class="button" id="notification-mark-read">Mark displayed notifications read</button>
 @else
 <p><a href="/login">Sign in</a> to receive notifications.</p>
 @endauth
 <a href="/notifications">Notification settings</a>
</section>
<div id="message-popups" class="message-popups" aria-live="polite" aria-relevant="additions"></div>
