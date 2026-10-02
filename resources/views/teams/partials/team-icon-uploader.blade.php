<button type="button" class="team-icon-uploader" data-team-icon-upload data-team-slug="{{ $slug }}" title="Upload team icon" aria-label="Upload team icon for {{ $name }}">
  <img src="/team-icons/{{ $slug }}?v=1" alt="{{ $name }} team icon">
</button>
<input type="file" class="team-icon-file-input" data-team-icon-input data-team-slug="{{ $slug }}" accept="image/png,image/jpeg,image/webp" hidden>
