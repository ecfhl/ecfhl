(() => {
  const table = document.querySelector('.season-player-table');
  if (table) {
    const playerColumn = table.querySelector('thead .player-frozen-player');
    const rankColumn = table.querySelector('thead .player-frozen-rank');
    const scroll = table.closest('.player-table-scroll');
    const updateOffset = () => scroll.style.setProperty('--player-sticky-offset', `${rankColumn.getBoundingClientRect().width + playerColumn.getBoundingClientRect().width}px`);
    updateOffset();
    if (typeof ResizeObserver !== 'undefined') {
      const observer = new ResizeObserver(updateOffset);
      observer.observe(rankColumn);
      observer.observe(playerColumn);
    }
    else window.addEventListener('resize', updateOffset);
  }
  const button = document.getElementById('season-player-more');
  if (!button) return;
  const rows = document.getElementById('season-player-rows');
  const count = document.getElementById('season-player-count');
  const error = document.getElementById('season-player-error');
  button.addEventListener('click', async () => {
    if (button.disabled || !button.dataset.nextUrl) return;
    button.disabled = true;
    button.textContent = 'Loading…';
    error.textContent = '';
    try {
      const response = await fetch(button.dataset.nextUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error('Request failed');
      const data = await response.json();
      if (typeof data.html !== 'string' || !Number.isInteger(data.shown) || !Number.isInteger(data.total)) throw new Error('Invalid response');
      rows.insertAdjacentHTML('beforeend', data.html);
      count.textContent = `Showing ${data.shown.toLocaleString()} of ${data.total.toLocaleString()} players`;
      button.dataset.nextUrl = data.next_url || '';
      button.hidden = !data.next_url;
    } catch (_) {
      error.textContent = 'Could not load more players. Please try again.';
    } finally {
      button.disabled = false;
      button.textContent = 'Show 25 more';
    }
  });
})();
