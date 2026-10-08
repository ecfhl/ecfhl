<section class="chat-widget card" data-chat-widget data-other="{{ $chatOther ?? '' }}">
 <div class="chat-heading"><h2>{{ $chatTitle ?? 'League chat' }}</h2>@if(!request()->is('messages'))<a href="/messages">All messages →</a>@endif</div>
 <button type="button" class="button chat-older" hidden>Earlier messages</button>
 <div class="chat-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
 <form class="chat-form"><label class="sr-only">Message</label><textarea aria-label="Message" placeholder="Write a message…" maxlength="4000" rows="2" required></textarea><button type="submit" class="button primary">Send</button></form>
 <p class="chat-status" role="status" aria-live="polite"></p>
</section>
