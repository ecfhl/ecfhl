(() => {
  // Filters replace the players panel without a document navigation or forced scroll.
  let dispose = () => {};
  let pending;
  const initialize = () => {
    const cleanups = [];
    const listen = (node, type, handler, options) => {
      node.addEventListener(type, handler, options);
      cleanups.push(() => node.removeEventListener(type, handler, options));
    };
    const observe = (observer, nodes) => {
      nodes.forEach(node => { if (node) observer.observe(node); });
      cleanups.push(() => observer.disconnect());
    };
    dispose = () => cleanups.forEach(cleanup => cleanup());
    const tips = document.getElementById('player-tips');
    if (tips) {
      const trigger = tips.querySelector('.player-tips-trigger');
      const panel = tips.querySelector('.player-tips-panel');
      let pinned = false;
      const show = open => { panel.hidden = !open; trigger.setAttribute('aria-expanded', String(open)); };
      listen(tips, 'pointerenter', event => { if (event.pointerType === 'mouse') show(true); });
      listen(tips, 'pointerleave', event => { if (event.pointerType === 'mouse' && !pinned && !tips.contains(document.activeElement)) show(false); });
      listen(trigger, 'focus', () => show(true));
      listen(trigger, 'click', () => { pinned = !pinned; show(pinned); });
      listen(tips, 'focusout', event => { if (!tips.contains(event.relatedTarget) && !pinned) show(false); });
      listen(document, 'click', event => { if (!tips.contains(event.target)) { pinned = false; show(false); } });
      listen(document, 'keydown', event => { if (event.key === 'Escape') { pinned = false; show(false); } });
    }
    const table = document.querySelector('.season-player-table');
    if (table) {
      const teamColumn = table.querySelector('thead .player-frozen-team');
      const scroll = table.closest('.player-table-scroll');
      const updateOffset = () => scroll.style.setProperty('--player-sticky-offset', `${teamColumn.getBoundingClientRect().width}px`);
      let syncColumns = updateOffset;
      updateOffset();
      if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(updateOffset);
        observe(observer, [teamColumn]);
      }
      else listen(window, 'resize', updateOffset);
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
        syncColumns = () => { updateOffset(); scheduleHeader(); };
        listen(window, 'scroll', scheduleHeader, { passive: true });
        listen(window, 'resize', scheduleHeader);
        listen(scroll, 'scroll', scheduleHeader, { passive: true });
        listen(frozen, 'scroll', () => {
          if (scroll.scrollLeft !== frozen.scrollLeft) scroll.scrollLeft = frozen.scrollLeft;
        }, { passive: true });
        if (typeof ResizeObserver !== 'undefined') {
          const observer = new ResizeObserver(scheduleHeader);
          observe(observer, [table, scroll, siteHeader, seasonBar]);
        }
        updateHeader();
      }
      if (window.EcfhlPlayerColumns) cleanups.push(window.EcfhlPlayerColumns.attach(table, scroll, frozen, syncColumns));
    }
    const button = document.getElementById('season-player-more');
    if (!button) return;
    const rows = document.getElementById('season-player-rows');
    const count = document.getElementById('season-player-count');
    const error = document.getElementById('season-player-error');
    let observer;
    let disposed = false;
    let request;
    const watchEnd = () => {
      observer?.disconnect();
      if (button.dataset.nextUrl && rows.lastElementChild) observer?.observe(rows.lastElementChild);
    };
    cleanups.push(() => { disposed = true; request?.abort(); observer?.disconnect(); });
    const loadMore = async () => {
      if (button.disabled || !button.dataset.nextUrl) return;
      if (pending || disposed) return;
      request = new AbortController();
      observer?.disconnect();
      button.disabled = true;
      button.textContent = 'Loading…';
      error.textContent = '';
      try {
        const response = await fetch(button.dataset.nextUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: request.signal });
        if (!response.ok) throw new Error('Request failed');
        const data = await response.json();
        if (typeof data.html !== 'string' || !Number.isInteger(data.shown) || !Number.isInteger(data.total)) throw new Error('Invalid response');
        if (disposed) return;
        rows.insertAdjacentHTML('beforeend', data.html);
        count.textContent = `Showing ${data.shown.toLocaleString()} of ${data.total.toLocaleString()} players`;
        button.dataset.nextUrl = data.next_url || '';
        button.hidden = !data.next_url;
        watchEnd();
      } catch (_) {
        if (disposed) return;
        button.classList.remove('player-auto-more');
        error.textContent = 'Could not load more players. Please try again.';
      } finally {
        button.disabled = false;
        button.textContent = 'Show 25 more';
      }
    };
    listen(button, 'click', loadMore);
    if (typeof IntersectionObserver !== 'undefined') {
      button.classList.add('player-auto-more');
      observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) loadMore();
      }, {rootMargin: '0px 0px 120px 0px'});
      watchEnd();
    }
  };
  initialize();

  const filter = async (url, push = true, focus = null, savedScroll = null) => {
    pending?.abort();
    const controller = new AbortController();
    pending = controller;
    const panel = document.querySelector('.season-players');
    if (!panel) return;
    panel.setAttribute('aria-busy', 'true');
    document.getElementById('season-player-error').textContent = '';
    try {
      const response = await fetch(url, {
        headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: controller.signal,
      });
      if (!response.ok) throw new Error('Request failed');
      const html = await response.text();
      if (controller.signal.aborted) return;
      const next = new DOMParser().parseFromString(html, 'text/html').querySelector('.season-players');
      if (!next) throw new Error('Invalid response');
      const x = savedScroll?.x ?? window.scrollX;
      const y = savedScroll?.y ?? window.scrollY;
      const horizontal = panel.querySelector('.player-table-scroll').scrollLeft;
      const advanced = panel.querySelector('.player-advanced');
      next.querySelector('.player-advanced').open = advanced.open;
      // Keep a short/empty result set from shrinking the document under the viewport.
      next.style.minHeight = `${panel.getBoundingClientRect().height}px`;
      if (push) {
        history.replaceState({ ...history.state, playersScroll: { x, y } }, '', location.href);
        history.pushState({ playersScroll: { x, y } }, '', url);
      }
      dispose();
      panel.replaceWith(next);
      initialize();
      next.querySelector('.player-table-scroll').scrollLeft = horizontal;
      if (focus) {
        const control = [...next.querySelectorAll('a.player-filter, a.player-sort-link')]
          .find(link => (link.getAttribute('aria-label') || link.textContent.trim()) === focus);
        control?.focus({ preventScroll: true });
      }
      window.scrollTo({ left: x, top: y, behavior: 'instant' });
    } catch (error) {
      if (error.name !== 'AbortError') {
        document.getElementById('season-player-error').textContent = 'Could not update players. Please try again.';
      }
    } finally {
      if (pending === controller) {
        pending = null;
        document.querySelector('.season-players')?.removeAttribute('aria-busy');
      }
    }
  };
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('.season-players a.player-filter, .season-players a.player-sort-link, .player-fixed-header a.player-sort-link, .player-advanced-footer a');
    if (!link || link.target && link.target !== '_self') return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || url.pathname !== '/players') return;
    event.preventDefault();
    filter(url.href, true, link.getAttribute('aria-label') || link.textContent.trim());
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!form.matches('.player-slicers, .player-search')) return;
    event.preventDefault();
    const url = new URL(form.action, location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    filter(url.href);
  }, true);
  window.addEventListener('popstate', event => {
    if (location.pathname === '/players') filter(location.href, false, null, event.state?.playersScroll);
  });
})();
