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
      return [{team:player.team, player:player.name + (player.nhl ? ' (' + player.nhl + ')' : ''), points, stats:stats.join(' · ') || 'Points updated'}];
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
      const count = document.getElementById('live-score-updates-count');
      let baseline = read(document.querySelector('.current-matchup-list'));
      let total = 0;
      const show = open => {
        tray.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
      };
      toggle.addEventListener('click', () => show(tray.hidden));
      close.addEventListener('click', () => { show(false); toggle.focus({preventScroll:true}); });
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
          if (baseline && baseline.date !== next.date) { list.replaceChildren(); total = 0; count.hidden = true; show(false); }
          baseline = next;
          if (!events.length) return;
          list.querySelector('.live-score-updates-empty')?.remove();
          const batch = element('div', 'live-score-update-batch');
          batch.append(element('time', 'live-score-update-time', new Date().toLocaleTimeString([], {hour:'numeric', minute:'2-digit'})));
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
          show(true);
        }
      };
    }
  };
})();
