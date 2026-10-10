<section id="notification-panel" class="communication-panel" aria-label="Notifications" hidden>
 <div class="communication-panel-heading" id="notification-handle"><h2>Notifications</h2><div class="notification-panel-actions">
 <button type="button" id="notification-trash" disabled aria-label="Clear notifications" title="Clear notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>

 <button type="button" id="notification-minimize" aria-label="Minimize notifications" title="Minimize">−</button><button type="button" id="notification-maximize" aria-label="Maximize notifications" title="Maximize">⛶</button>
 <button type="button" data-close-notifications aria-label="Close notifications" title="Close">×</button></div></div>

 <div class="notification-panel-body">
 @auth
 <div id="notification-inbox" class="communication-log" role="log" aria-live="polite" aria-relevant="additions"></div>
 <p id="notification-panel-status" role="status" hidden></p>
 @else
 <p><a href="/login">Sign in</a> to receive notifications.</p>
 @endauth
 </div>
</section>
<div id="message-popups" class="message-popups" aria-live="polite" aria-relevant="additions"></div>
