<button type="button" class="team-icon-uploader" data-team-icon-viewer data-team-slug="{{ $slug }}" data-team-name="{{ $name }}" title="View team icon" aria-label="View team icon for {{ $name }}">
  <img src="{{ \App\Support\TeamImages::url($slug,64) }}" srcset="{{ \App\Support\TeamImages::url($slug,64) }} 1x, {{ \App\Support\TeamImages::url($slug,160) }} 2x" data-full-src="{{ \App\Support\TeamImages::url($slug) }}" width="64" height="64" alt="{{ $name }} team icon" loading="lazy" decoding="async">
</button>
