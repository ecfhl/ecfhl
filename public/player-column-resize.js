(() => {
  window.EcfhlPlayerColumns = {
    attach(table, scroll, frozen, changed) {
      const columns = [...table.querySelectorAll('col[data-column]')];
      const tables = [table, frozen?.querySelector('table')].filter(Boolean);
      const handles = tables.flatMap(node => [...node.querySelectorAll('[data-player-resize]')]);
      const cleanups = [];
      let overrides = {}, storageKey, drag;
      const limits = key => key === 'team' ? [40, 160] : key === 'player' ? [110, 420] : ['today', 'tomorrow'].includes(key) ? [58, 240] : [42, 200];
      const clamp = (key, value) => Math.round(Math.max(limits(key)[0], Math.min(limits(key)[1], value)));
      const listen = (node, event, handler) => {
        node.addEventListener(event, handler);
        cleanups.push(() => node.removeEventListener(event, handler));
      };
      const load = () => {
        const key = `ecfhl-player-columns-v1:${window.innerWidth <= 600 ? 'phone' : 'wide'}`;
        if (key === storageKey) return;
        storageKey = key;
        overrides = {};
        try {
          const saved = JSON.parse(localStorage.getItem(key) || '{}');
          for (const col of columns) {
            const name = col.dataset.column;
            if (Object.prototype.hasOwnProperty.call(saved || {}, name) && Number.isFinite(saved[name])) overrides[name] = clamp(name, saved[name]);
          }
        } catch (_) { /* Resizing still works when storage is unavailable. */ }
      };
      const save = () => {
        try { localStorage.setItem(storageKey, JSON.stringify(overrides)); } catch (_) {}
      };
      let widths = {};
      const defaults = () => {
        const phone = window.innerWidth <= 600;
        const player = phone ? Math.round(Math.max(125, Math.min(160, scroll.clientWidth * .4))) : 200;
        const game = phone ? Math.max(58, Math.floor((scroll.clientWidth - 44 - player) / 2)) : 120;
        return { team: 44, player, today: game, tomorrow: game };
      };
      const apply = () => {
        const sizes = defaults();
        widths = Object.fromEntries(columns.map(col => {
          const key = col.dataset.column;
          return [key, overrides[key] ?? sizes[key] ?? 72];
        }));
        for (const node of tables) {
          node.style.width = `${Object.values(widths).reduce((sum, width) => sum + width, 0)}px`;
          for (const col of node.querySelectorAll('col[data-column]')) col.style.width = `${widths[col.dataset.column]}px`;
        }
        for (const node of [scroll, frozen].filter(Boolean)) {
          node.style.setProperty('--player-column-width', `${widths.player}px`);
          node.style.setProperty('--team-column-width', `${widths.team}px`);
        }
        for (const handle of handles) {
          const key = handle.dataset.playerResize;
          handle.setAttribute('aria-valuemin', limits(key)[0]);
          handle.setAttribute('aria-valuemax', limits(key)[1]);
          handle.setAttribute('aria-valuenow', widths[key]);
          handle.setAttribute('aria-valuetext', `${widths[key]} pixels`);
        }
        changed();
      };
      const finish = cancel => {
        if (!drag) return;
        if (cancel) {
          if (drag.original === undefined) delete overrides[drag.key];
          else overrides[drag.key] = drag.original;
        } else save();
        drag.handle.classList.remove('is-resizing');
        if (drag.handle.hasPointerCapture(drag.pointerId)) drag.handle.releasePointerCapture(drag.pointerId);
        drag = null;
        document.documentElement.classList.remove('player-columns-resizing');
        apply();
      };
      for (const handle of handles) {
        const key = handle.dataset.playerResize;
        listen(handle, 'click', event => { event.preventDefault(); event.stopPropagation(); });
        listen(handle, 'pointerdown', event => {
          if (drag || event.button !== 0) return;
          event.preventDefault();
          event.stopPropagation();
          handle.focus({ preventScroll: true });
          drag = { key, start: event.clientX, width: widths[key], original: overrides[key], pointerId: event.pointerId, handle };
          handle.setPointerCapture(event.pointerId);
          handle.classList.add('is-resizing');
          document.documentElement.classList.add('player-columns-resizing');
        });
        listen(handle, 'dblclick', event => {
          event.preventDefault();
          delete overrides[key];
          save();
          apply();
        });
        listen(handle, 'keydown', event => {
          if (event.key === 'Escape') { finish(true); return; }
          if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
          event.preventDefault();
          overrides[key] = event.key === 'Home' ? limits(key)[0] : event.key === 'End' ? limits(key)[1] : clamp(key, widths[key] + (event.key === 'ArrowRight' ? 1 : -1) * (event.shiftKey ? 20 : 5));
          save();
          apply();
        });
      }
      listen(window, 'pointermove', event => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        overrides[drag.key] = clamp(drag.key, drag.width + event.clientX - drag.start);
        apply();
      });
      listen(window, 'pointerup', event => { if (drag && event.pointerId === drag.pointerId) finish(false); });
      listen(window, 'pointercancel', event => { if (drag && event.pointerId === drag.pointerId) finish(true); });
      listen(window, 'blur', () => finish(true));
      const reset = scroll.closest('.season-players').querySelector('.player-reset-widths');
      if (reset) listen(reset, 'click', () => { finish(true); overrides = {}; save(); apply(); });
      const resize = () => { finish(true); load(); apply(); };
      listen(window, 'resize', resize);
      if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(() => { if (!drag) apply(); });
        observer.observe(scroll);
        cleanups.push(() => observer.disconnect());
      }
      load();
      apply();
      return () => { finish(true); cleanups.forEach(cleanup => cleanup()); };
    },
  };
})();
