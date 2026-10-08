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
      return [{key:player.key, team:player.team, teamId:player.teamId, player:player.name + (player.nhl ? ' (' + player.nhl + ')' : ''), points, stats:stats.join(' · ') || 'Points updated'}];
    });
  };
  window.EcfhlScoreUpdates = {
    compare,
    start() {
      if (window.ecfhlScoringPopup) return window.ecfhlScoringPopup;
      const $ = id => document.getElementById('live-score-updates-' + id);
      const tray = document.getElementById('live-score-updates');
      if (!tray) return null;
      const list = $('list'), toggle = $('toggle');
      const close = $('close'), trash = $('trash'), minimize = $('minimize'), restore = $('restore');
      const count = $('count'), handle = $('handle'), scope = $('scope'), team = $('team');
      const storageKey = 'ecfhl-scoring-popup:' + (document.getElementById('communication-context')?.dataset.userId || 'guest');
      const filterKey='ecfhl-scoring-filters:'+(document.getElementById('communication-context')?.dataset.userId||'guest');
      let filters={};try{filters=JSON.parse(localStorage.getItem(filterKey)||'{}');}catch(_){}
      let saved;
      try { saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (_) {}
      let baseline = saved?.baseline || null, nhlBaseline = saved?.nhlBaseline || null;
      let history = Array.isArray(saved?.history) ? saved.history : [];
      let enabled = filters.enabled !== false;
      let mode = saved?.mode || 'closed';
      let position = saved?.position || null, drag = null;
      const savedScope=filters.scope||saved?.scope;
      scope.value = ['team','matchup','teams','league','nhl'].includes(savedScope) ? savedScope : 'matchup';
      const selectedTeam = String(team.dataset.accountTeam || '');
      let selectedTeams = Array.isArray(filters.teams||saved?.teams) ? (filters.teams||saved.teams).map(String) : [];
      const element = (tag, className, text) => {
        const node = document.createElement(tag); node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
      };
      const persist = () => {
        try { sessionStorage.setItem(storageKey, JSON.stringify({baseline,nhlBaseline,history,enabled,mode,position,scope:scope.value,team:selectedTeam,teams:selectedTeams})); } catch (_) {}
      };
      const constrain = () => {
        if (!position || tray.hidden) return;
        const rect = tray.getBoundingClientRect(), nav = document.querySelector('.mobile-primary-nav');
        const bottom = nav && getComputedStyle(nav).display !== 'none' ? nav.getBoundingClientRect().top : window.innerHeight;
        position.x = Math.max(8, Math.min(position.x, window.innerWidth - rect.width - 8));
        position.y = Math.max(8, Math.min(position.y, bottom - rect.height - 8));
        Object.assign(tray.style,{left:position.x+'px',top:position.y+'px',right:'auto'});
      };
      const render = () => {
        const total = history.reduce((sum,batch)=>sum+batch.events.length,0);
        tray.hidden = mode === 'closed'; tray.dataset.minimized = String(mode === 'minimized');
        restore.hidden = mode !== 'minimized'; restore.textContent = total + ' updates';
        trash.disabled = total === 0; count.textContent = total > 0 ? String(total) : ''; count.hidden = total === 0;
        count.dataset.active = String(total > 0);
        toggle.dataset.enabled = String(enabled); toggle.setAttribute('aria-expanded',String(mode !== 'closed'));
        toggle.setAttribute('aria-label','Open scoring updates, '+total+' stored, updates '+(enabled?'on':'off'));
        $('team-label').hidden = scope.value !== 'teams';
        $('team-summary').textContent = selectedTeams.length ? selectedTeams.length + ' selected' : 'Select teams';
        list.replaceChildren();
        if (!total) list.append(element('p','live-score-updates-empty','No updates'));
        const green = new Set((baseline?.players || []).filter(p=>p.change==='up').map(p=>p.key));
        const newest = new Set();
        history.forEach(batch => {
          const node = element('div','live-score-update-batch');
          node.append(element('time','live-score-update-time',batch.time));
          const teams = new Map(); batch.events.forEach(event=>{if(!teams.has(event.team))teams.set(event.team,[]);teams.get(event.team).push(event);});
          teams.forEach((plays,name)=>{
            const group = element('div','live-score-update-team'), heading = element('div','live-score-update-team-heading');
            heading.append(element('strong','',name));
            if (!batch.nhl) heading.append(element('span','live-score-update-points','+'+formatPoints(plays.reduce((sum,p)=>sum+p.points,0))+' FPts'));
            group.append(heading);
            plays.forEach(play=>{
              const row=element('div','live-score-update-player'), detail=element('div','');
              row.setAttribute('data-new',String(!newest.has(play.key)&&green.has(play.key)));newest.add(play.key);
              detail.append(element('strong','',play.player),element('span','live-score-update-stats',play.stats));row.append(detail);
              if (!batch.nhl) row.append(element('span','live-score-update-player-points','+'+formatPoints(play.points)));
              group.append(row);
            });node.append(group);
          });list.append(node);
        });
        if (!tray.hidden) constrain();persist();
      };
      // Opening the box never changes whether updates are received.
      toggle.addEventListener('click',()=>{mode='expanded';render();close.focus({preventScroll:true});});
      minimize.addEventListener('click',()=>{mode='minimized';render();restore.focus({preventScroll:true});});
      restore.addEventListener('click',()=>{mode='expanded';render();close.focus({preventScroll:true});});
      trash.addEventListener('click',()=>{history=[];mode='minimized';render();restore.focus({preventScroll:true});});
      close.addEventListener('click',()=>{mode='closed';render();toggle.focus({preventScroll:true});});
      tray.addEventListener('keydown',event=>{if(event.key==='Escape'){mode='closed';render();toggle.focus({preventScroll:true});}});
      const filtersChanged=()=>{try{localStorage.setItem(filterKey,JSON.stringify({enabled,scope:scope.value,teams:selectedTeams}));}catch(_){}history=[];nhlBaseline=null;render();window.dispatchEvent(new Event('ecfhl-scoring-filter'));};
      scope.addEventListener('change',filtersChanged);team.addEventListener('change',()=>{selectedTeams=[...team.querySelectorAll('input:checked')].map(input=>input.value);filtersChanged();});
      const accepts = event => {
        if (scope.value==='league')return true;
        if (scope.value==='teams')return selectedTeams.includes(String(event.teamId));
        if (scope.value==='team')return String(event.teamId)===selectedTeam;
        const pair=(baseline?.matchups||[]).find(pair=>[String(pair.away_team_id),String(pair.home_team_id)].includes(selectedTeam));
        return !!pair && [String(pair.away_team_id),String(pair.home_team_id)].includes(String(event.teamId));
      };
      const update = next => {
        if (!next || typeof next.date!=='string' || !Array.isArray(next.players))return;
        if (baseline && baseline.date!==next.date){baseline=null;history=[];nhlBaseline=null;mode='closed';}
        if (Array.isArray(next.teams)) {
          const ids=next.teams.map(t=>String(t.id));
          selectedTeams=selectedTeams.filter(id=>ids.includes(id));
          team.replaceChildren(...next.teams.map(t=>{
            const label=element('label',''),input=element('input','');
            input.type='checkbox';input.value=String(t.id);input.checked=selectedTeams.includes(input.value);
            label.append(input,element('span','',t.name));return label;
          }));
        }
        let events=compare(baseline,next);baseline=next;events=events.filter(accepts);
        if(scope.value==='nhl'){
          const current=next.nhlEvents;
          events=Array.isArray(current)&&nhlBaseline?current.filter(e=>!nhlBaseline.includes(e.key)):[];
          if(Array.isArray(current))nhlBaseline=current.map(e=>e.key);
        }else nhlBaseline=null;
        if(enabled && events.length){
          history.unshift({time:new Date().toLocaleTimeString([],{hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true}),events,nhl:scope.value==='nhl'});
          // Retain the most recent 250 batches per tab to keep navigation state bounded.
          history=history.slice(0,250);if(mode==='closed')mode='minimized';
        }
        render();
      };
      handle.addEventListener('pointerdown',event=>{
        if(event.button!==0 || event.target.closest('button,a,select,input,label'))return;
        const rect=tray.getBoundingClientRect();drag={id:event.pointerId,x:event.clientX,y:event.clientY,left:rect.left,top:rect.top};handle.setPointerCapture(event.pointerId);tray.dataset.dragging='true';
      });
      handle.addEventListener('pointermove',event=>{if(!drag||drag.id!==event.pointerId)return;position={x:drag.left+event.clientX-drag.x,y:drag.top+event.clientY-drag.y};constrain();});
      const end=event=>{if(!drag||drag.id!==event.pointerId)return;if(handle.hasPointerCapture(event.pointerId))handle.releasePointerCapture(event.pointerId);drag=null;tray.dataset.dragging='false';persist();};
      ['pointerup','pointercancel','lostpointercapture'].forEach(type=>handle.addEventListener(type,end));
      handle.addEventListener('keydown',event=>{
        if(event.target!==handle || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
        event.preventDefault();const rect=tray.getBoundingClientRect(),step=event.shiftKey?30:10;
        position={x:rect.left+(event.key==='ArrowLeft'?-step:event.key==='ArrowRight'?step:0),y:rect.top+(event.key==='ArrowUp'?-step:event.key==='ArrowDown'?step:0)};constrain();persist();
      });window.addEventListener('resize',constrain);
      tray.addEventListener('ecfhl-panel-resize',event=>{if(event.detail?.position)position=event.detail.position;constrain();persist();});
      render();
      return window.ecfhlScoringPopup={scope:()=>scope.value,update,enabled:()=>enabled,setEnabled(value){
        enabled=Boolean(value);baseline=null;nhlBaseline=null;
        if(!enabled)mode='closed';
        try{localStorage.setItem(filterKey,JSON.stringify({enabled,scope:scope.value,teams:selectedTeams}));}catch(_){}
        render();window.dispatchEvent(new Event('ecfhl-scoring-filter'));
      }};
    }
  };
})();

