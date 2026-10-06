(() => {
  window.EcfhlLiveScoring = {
    start({saveOpenMatchups, highlightNotificationTeam}) {
      let refreshing = false;
      const blocked = () => document.hidden || document.querySelector('dialog[open],#team-icon-modal.open');
      const refresh = async () => {
        const current = document.querySelector('.current-matchup-list');
        if (!current || blocked() || refreshing) return;
        refreshing = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
          const response = await fetch(location.href, {headers:{'X-Requested-With':'XMLHttpRequest',Accept:'text/html'},cache:'no-store',signal:controller.signal});
          if (!response.ok) throw new Error('Refresh failed');
          const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
          const fresh = doc.querySelector('.current-matchup-list');
          if (!fresh) throw new Error('Missing matchups');
          if (blocked()) return;
          // Capture after fetching: user interactions during the request win.
          const open = new Map([...current.querySelectorAll('[data-matchup-key]')].map(card=>[card.dataset.matchupKey,card.open]));
          fresh.querySelectorAll('[data-matchup-key]').forEach(card=>card.open=open.get(card.dataset.matchupKey) ?? false);
          const focused = document.activeElement;
          const focusKey = current.contains(focused) ? {matchup:focused.closest('[data-matchup-key]')?.dataset.matchupKey,href:focused.getAttribute('href'),player:focused.dataset.playerId,slug:focused.dataset.teamSlug,summary:focused.tagName==='SUMMARY'} : null;
          const x=window.scrollX,y=window.scrollY;
          saveOpenMatchups();
          current.replaceWith(fresh);
          highlightNotificationTeam();
          for (const selector of ['.team-updated','.current-teams-page > .matchup-period-label']) {
            const old=document.querySelector(selector), replacement=doc.querySelector(selector);
            if (old && replacement) old.replaceWith(replacement);
          }
          if (focusKey) {
            const card=[...fresh.querySelectorAll('[data-matchup-key]')].find(el=>el.dataset.matchupKey===focusKey.matchup);
            const replacement=focusKey.summary ? card?.querySelector('summary') : [...(card?.querySelectorAll('a,button') ?? [])].find(el=>focusKey.href ? el.getAttribute('href')===focusKey.href : focusKey.slug && el.dataset.teamSlug===focusKey.slug);
            replacement?.focus({preventScroll:true});
          }
          window.scrollTo(x,y);
        } catch (error) {
          const status=document.querySelector('.team-updated');
          if (status && !status.querySelector('[data-refresh-error]')) {
            const warning=document.createElement('span'); warning.dataset.refreshError=''; warning.textContent=' · Refresh delayed; showing saved scores and retrying automatically.';status.append(warning);
          }
        } finally {clearTimeout(timeout);refreshing=false;}
      };
      setInterval(refresh,60000);
      document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
      return refresh;
    }
  };
})();
