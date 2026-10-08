<section id="notification-panel" class="communication-panel" aria-label="Notifications" hidden>
 <div class="communication-panel-heading"><h2>Notifications</h2><button type="button" data-close-notifications aria-label="Close notifications">×</button></div>
 <div class="notification-panel-body">
 @auth
 <label class="communication-setting"><input type="checkbox" id="header-notifications-enabled" checked> Notifications enabled</label>
 <p id="header-push-state" role="status"></p><button type="button" class="button" id="header-enable-push">Enable browser notifications</button>
 <div id="notification-inbox" class="communication-log"></div>
 <div class="notification-panel-footer"><button type="button" class="button" id="notification-mark-read">Mark all read</button><a href="/notifications">Settings →</a></div>
 @else
 <p><a href="/login">Sign in</a> to receive notifications.</p>
 @endauth
 </div>
</section>
<div id="message-popups" class="message-popups" aria-live="polite" aria-relevant="additions"></div>
