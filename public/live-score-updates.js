(() => {
  const formatPoints = value => Number(value.toFixed(2)).toString();
  const compare = (previous, next) => {
    if (!previous || previous.date !== next.date) return [];
    const oldPlayers = new Map(previous.players.map(player => [player.key, player]));
    return next.players.flatMap(player => {
      const old = oldPlayers.get(player.key);
      const points = player.points - (old?.points ?? player.points);
      if (!old || !Number.isFinite(points) || points < .0001) return [];
      const labels = player.goalie
        ? {W:'win', L:'loss', OTL:'OTL', SO:'shutout'}
        : {G:'goal', A:'assist', PPG:'PPG', SHG:'SHG', GWG:'GWG'};
      const stats = Object.entries(labels).flatMap(([key, label]) => {
        const gain = (player.stats[key] || 0) - (old.stats[key] || 0);
        if (gain <= 0) return [];
        if (key === 'GWG') return ['GWG'];
        return [gain + ' ' + label + (gain !== 1 && ['goal','assist','win','shutout'].includes(label) ? 's' : gain !== 1 && label === 'loss' ? 'es' : '')];
      });
      return [{key:player.key, team:player.team, player:player.name + (player.nhl ? ' (' + player.nhl + ')' : ''), points, stats:stats.join(' · ') || 'Points updated'}];
    });
  };
  const read = panel => {
    try {
      const state = JSON.parse(panel?.dataset.scoringState || 'null');
      return state && typeof state.date === 'string' && Array.isArray(state.players) ? state : null;
    } catch (_) { return null; }
  };
  window.EcfhlScoreUpdates = {
    compare,
    start() {
      const tray = document.getElementById('live-score-updates');
      if (!tray) return null;
      const list = document.getElementById('live-score-updates-list');
      const toggle = document.getElementById('live-score-updates-toggle');
      const close = document.getElementById('live-score-updates-close');
      const minimize = document.getElementById('live-score-updates-minimize');
      const count = document.getElementById('live-score-updates-count');
      const handle = document.getElementById('live-score-updates-handle');
      let position = null, drag = null;
      const constrain = () => {
        if (!position || tray.hidden) return;
        const rect = tray.getBoundingClientRect();
        const nav = document.querySelector('.mobile-primary-nav');
        const bottom = nav && getComputedStyle(nav).display !== 'none' ? nav.getBoundingClientRect().top : window.innerHeight;
        position.x = Math.max(8, Math.min(position.x, window.innerWidth - rect.width - 8));
        position.y = Math.max(8, Math.min(position.y, bottom - rect.height - 8));
        tray.style.left = position.x + 'px';
        tray.style.top = position.y + 'px';
        tray.style.right = 'auto';
      };
      handle.addEventListener('pointerdown', event => {
        if (event.button !== 0 || event.target.closest('button')) return;
        const rect = tray.getBoundingClientRect();
        drag = {id:event.pointerId, x:event.clientX, y:event.clientY, left:rect.left, top:rect.top};
        handle.setPointerCapture(event.pointerId);
        tray.setAttribute('data-dragging', 'true');
      });
      handle.addEventListener('pointermove', event => {
        if (!drag || event.pointerId !== drag.id) return;
        position = {x:drag.left + event.clientX - drag.x, y:drag.top + event.clientY - drag.y};
        constrain();
      });
      const endDrag = event => {
        if (!drag || event.pointerId !== drag.id) return;
        if (handle.hasPointerCapture(event.pointerId)) handle.releasePointerCapture(event.pointerId);
        drag = null;
        tray.setAttribute('data-dragging', 'false');
      };
      handle.addEventListener('pointerup', endDrag);
      handle.addEventListener('pointercancel', endDrag);
      handle.addEventListener('lostpointercapture', endDrag);
      handle.addEventListener('keydown', event => {
        if (event.target.closest('button') || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const rect = tray.getBoundingClientRect(), step = event.shiftKey ? 30 : 10;
        position = {x:rect.left + (event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0), y:rect.top + (event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0)};
        constrain();
      });
      window.addEventListener('resize', constrain);
      let baseline = read(document.querySelector('.current-matchup-list'));
      let total = 0, cleared = false;
      const highlighted = new Map();
      const hasGreenScore = panel => !!panel?.querySelector('.score-up');
      const syncAlertState = panel => {
        const active = !cleared && hasGreenScore(panel);
        toggle.dataset.enabled = String(active);
        toggle.setAttribute('aria-pressed', String(active));
        toggle.setAttribute('aria-label', active ? 'Scoring updates available' : 'Show scoring updates');
        toggle.title = active ? 'Scoring updates available' : 'Show scoring updates';
      };
      const show = open => {
        tray.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        syncAlertState(document.querySelector('.current-matchup-list'));
        if (open) constrain();
      };
      syncAlertState(document.querySelector('.current-matchup-list'));
      toggle.addEventListener('click', () => show(tray.hidden));
      minimize.addEventListener('click', () => { show(false); toggle.focus({preventScroll:true}); });
      close.addEventListener('click', () => {
        cleared = true; total = 0; highlighted.clear();
        list.replaceChildren(element('p', 'live-score-updates-empty', 'No updates'));
        count.textContent = '0'; count.hidden = true;
        toggle.setAttribute('data-has-updates', 'false');
        show(false); toggle.focus({preventScroll:true});
      });
      tray.addEventListener('keydown', event => {
        if (event.key === 'Escape') { show(false); toggle.focus({preventScroll:true}); }
      });
      const element = (tag, className, text) => {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
      };
      return {
        update(panel) {
          const next = read(panel);
          if (!next) return;
          const events = compare(baseline, next);
          if (baseline && baseline.date !== next.date) { list.replaceChildren(element('p', 'live-score-updates-empty', 'No updates')); total = 0; cleared = false; highlighted.clear(); count.hidden = true; toggle.setAttribute('data-has-updates', 'false'); show(false); }
          baseline = next;
          if (events.length) cleared = false;
          const green = new Set(next.players.filter(player => player.change === 'up').map(player => player.key));
          highlighted.forEach((row, key) => row.setAttribute('data-new', String(green.has(key))));
          syncAlertState(panel);
          if (!events.length) return;
          list.querySelector('.live-score-updates-empty')?.remove();
          const batch = element('div', 'live-score-update-batch');
          batch.append(element('time', 'live-score-update-time', new Date().toLocaleTimeString([], {hour:'numeric', minute:'2-digit', second:'2-digit', hour12:true})));
          const teams = new Map();
          events.forEach(event => {
            if (!teams.has(event.team)) teams.set(event.team, []);
            teams.get(event.team).push(event);
          });
          teams.forEach((plays, team) => {
            const group = element('div', 'live-score-update-team');
            const heading = element('div', 'live-score-update-team-heading');
            heading.append(element('strong', '', team), element('span', 'live-score-update-points', '+' + formatPoints(plays.reduce((sum, play) => sum + play.points, 0)) + ' FPts'));
            group.append(heading);
            plays.forEach(play => {
              const row = element('div', 'live-score-update-player');
              highlighted.get(play.key)?.setAttribute('data-new', 'false');
              row.setAttribute('data-new', String(green.has(play.key)));
              highlighted.set(play.key, row);
              const detail = element('div', '');
              detail.append(element('strong', '', play.player), element('span', 'live-score-update-stats', play.stats));
              row.append(detail, element('span', 'live-score-update-player-points', '+' + formatPoints(play.points)));
              group.append(row);
            });
            batch.append(group);
          });
          list.prepend(batch);
          list.scrollTop = 0;
          total += events.length;
          count.textContent = String(total);
          count.hidden = false;
          toggle.setAttribute('data-has-updates', 'true');
          show(true);
        }
      };
    }
  };
})();
