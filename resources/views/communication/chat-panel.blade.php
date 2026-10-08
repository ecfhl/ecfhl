@auth
<section id="chat-panel" class="communication-panel floating-chat" aria-label="League and team messages" hidden>
 <div id="chat-panel-handle" class="communication-panel-heading" tabindex="0" role="group" aria-label="Move chat: drag or use arrow keys"><h2>Messages</h2><div class="notification-panel-actions">
 
 <a class="notification-settings" href="/notifications#alerts" aria-label="Chat alert settings" title="Chat alert settings">⚙</a><button type="button" id="chat-panel-minimize" aria-label="Minimize chat" title="Minimize">−</button><button type="button" class="panel-header-restore" disabled aria-label="Restore panel" title="Restore">□</button>
 <button type="button" id="chat-panel-maximize" aria-label="Maximize chat" title="Maximize">⛶</button><button type="button" id="chat-panel-close" aria-label="Close chat" title="Close">×</button></div></div>
 <button type="button" id="chat-panel-restore" class="notification-restore" hidden aria-label="Expand chat">League chat</button>
 <div class="chat-panel-body">
 <label class="chat-conversation-label" for="chat-panel-conversation">Chat with</label><select id="chat-panel-conversation"><option value="">League chat</option></select>
 <button type="button" id="chat-panel-older" class="button chat-older" hidden>Earlier messages</button>
 <div id="chat-panel-log" class="chat-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
 <form id="chat-panel-form" class="chat-form"><textarea id="chat-panel-text" aria-label="Message" placeholder="Write a message…" maxlength="4000" rows="2" required></textarea><button type="submit" class="button primary">Send</button></form>
 <p id="chat-panel-status" class="chat-status" role="status" aria-live="polite"></p>
 </div>
</section>
@endauth
