<section class="chat-widget card">
 <div class="chat-heading"><h2>{{ $chatTitle ?? 'League chat' }}</h2><a class="button" href="/messages{{ !empty($chatOther) ? '?user_id='.$chatOther : '' }}">Open chat</a></div>
</section>
