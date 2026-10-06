(() => {
  // Filter navigation must not move the viewport. Browser restoration can race
  // our script on mobile, so disable native restoration and restore only after
  // the new page has completed layout.
  const restoreKey = 'seasonPlayersScrollY';
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  const saveScroll = () => sessionStorage.setItem(restoreKey, String(window.scrollY));
  const savedScroll = sessionStorage.getItem(restoreKey);
  if (savedScroll !== null) {
    const y = Number(savedScroll) || 0;
    const restore = () => window.scrollTo(0, y);
    requestAnimationFrame(restore);
    window.addEventListener('load', () => {
      restore();
      requestAnimationFrame(restore);
      setTimeout(restore, 100);
      setTimeout(() => {
        restore();
        sessionStorage.removeItem(restoreKey);
      }, 300);
    }, { once: true });
  }
  document.addEventListener('click', event => {
    const link = event.target.closest('a.player-filter, a.player-sort-link');
    if (!link || !link.href) return;
    saveScroll();
  });
  document.querySelectorAll('.player-slicers, .player-search').forEach(form => {
    form.addEventListener('submit', saveScroll);
  });
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
    const frozen = document.getElementById('season-player-fixed-header');
    if (frozen) {
      const header = table.querySelector('thead');
      const clone = table.cloneNode(false);
      clone.append(table.querySelector('colgroup').cloneNode(true), header.cloneNode(true));
      frozen.append(clone);
      const siteHeader = document.querySelector('.site-header');
      const seasonBar = document.querySelector('.season-filter-bar');
      const updateHeader = () => {
        const rect = scroll.getBoundingClientRect();
        const top = Math.max(0, siteHeader?.getBoundingClientRect().bottom || 0, seasonBar?.getBoundingClientRect().bottom || 0);
        const height = header.getBoundingClientRect().height;
        frozen.hidden = header.getBoundingClientRect().top >= top || rect.bottom <= top + height;
        if (frozen.hidden) return;
        const styles = getComputedStyle(scroll);
        for (const key of ['--rank-column-width', '--player-column-width', '--team-column-width', '--player-stat-width', '--player-stat-count', '--player-sticky-offset']) {
          frozen.style.setProperty(key, styles.getPropertyValue(key));
        }
        frozen.style.top = `${top}px`;
        frozen.style.left = `${rect.left}px`;
        frozen.style.width = `${scroll.clientWidth}px`;
        clone.style.width = `${table.getBoundingClientRect().width}px`;
        frozen.scrollLeft = scroll.scrollLeft;
      };
      let queued = false;
      const scheduleHeader = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; updateHeader(); });
      };
      window.addEventListener('scroll', scheduleHeader, { passive: true });
      window.addEventListener('resize', scheduleHeader);
      scroll.addEventListener('scroll', scheduleHeader, { passive: true });
      frozen.addEventListener('scroll', () => {
        if (scroll.scrollLeft !== frozen.scrollLeft) scroll.scrollLeft = frozen.scrollLeft;
      }, { passive: true });
      if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(scheduleHeader);
        for (const node of [table, scroll, siteHeader, seasonBar]) if (node) observer.observe(node);
      }
      updateHeader();
    }
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
