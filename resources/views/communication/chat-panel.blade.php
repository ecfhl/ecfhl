@auth
<section id="chat-panel" class="communication-panel floating-chat" aria-label="League and team messages" hidden>
 <div id="chat-panel-handle" class="communication-panel-heading" tabindex="0" role="group" aria-label="Move chat: drag or use arrow keys"><h2>Chat</h2><div class="notification-panel-actions">
 
<button type="button" id="chat-panel-minimize" aria-label="Minimize chat" title="Minimize">−</button>
 <button type="button" id="chat-panel-maximize" aria-label="Maximize chat" title="Maximize">⛶</button><button type="button" id="chat-panel-close" aria-label="Close chat" title="Close">×</button></div></div>
 <button type="button" id="chat-panel-restore" class="notification-restore" hidden aria-label="Expand chat">League chat</button>
 <div class="chat-panel-body">
 <label class="chat-conversation-label" for="chat-panel-conversation">Chat with</label><select id="chat-panel-conversation"><option value="">League chat</option></select>
 <button type="button" id="chat-panel-older" class="button chat-older" hidden>Earlier messages</button>
 <div id="chat-panel-log" class="chat-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
 <form id="chat-panel-form" class="chat-form chat-composer">
  <textarea id="chat-panel-text" aria-label="Message" placeholder="Write a message…" maxlength="4000" rows="2"></textarea>
  <div id="chat-panel-attachment" class="chat-attachment-preview" hidden><img alt="Selected image preview" hidden><span></span><button type="button" aria-label="Remove attached image">×</button></div>
  <div class="chat-composer-toolbar" role="toolbar" aria-label="Message formatting">
   <button type="button" data-chat-format="bold" aria-label="Bold"><b>B</b></button><button type="button" data-chat-format="italic" aria-label="Italic"><i>I</i></button><button type="button" data-chat-format="strike" aria-label="Strikethrough"><s>S</s></button><button type="button" data-chat-format="link" aria-label="Insert link">🔗</button><button type="button" data-chat-format="list" aria-label="Bullet list">☷</button><button type="button" data-chat-format="emoji" aria-label="Insert emoji">☺</button><button type="button" data-chat-format="gif" aria-label="Share GIF" aria-expanded="false" aria-controls="chat-panel-gifs">GIF</button><button type="button" data-chat-format="image" aria-label="Attach image">▧</button>
   <button type="submit" class="chat-send" aria-label="Send message">➤</button>
  </div>
  <div id="chat-panel-emoji" class="chat-emoji-picker" hidden>@foreach(['👍','😂','🏒','🔥','🎉','💀','❤️','😎'] as $emoji)<button type="button" data-chat-emoji="{{ $emoji }}" aria-label="Insert {{ $emoji }}">{{ $emoji }}</button>@endforeach</div>
  <section id="chat-panel-gifs" class="chat-gif-picker" aria-label="Choose a GIF" hidden>
   <div class="chat-gif-heading"><strong>Pick a GIF</strong><button id="chat-panel-gif-close" type="button" aria-label="Close GIF picker">×</button></div>
   <input id="chat-panel-gif-search" type="search" aria-label="Filter reaction GIFs" placeholder="Find a reaction…" autocomplete="off">
   <div id="chat-panel-gif-grid" class="chat-gif-grid"></div>
   <div class="chat-gif-footer"><button id="chat-panel-gif-upload" type="button">Upload a GIF</button><a href="https://giphy.com" target="_blank" rel="noopener noreferrer">GIFs from GIPHY</a></div>
   <small>Tap a GIF, then send. Uploads: up to 2 MB.</small>
  </section>
  <input id="chat-panel-gif-file" type="file" accept="image/gif" hidden>
  <input id="chat-panel-file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
 </form>
 <p id="chat-panel-status" class="chat-status" role="status" aria-live="polite"></p>
 </div>
</section>
@endauth
