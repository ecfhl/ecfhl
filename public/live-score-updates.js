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
      const gain=key=>(player.stats[key]||0)-(old.stats[key]||0);
      const goals=gain('G');
      const action=goals>0?(gain('PPG')>0?'Power play goal':gain('SHG')>0?'Short handed goal':goals>1?goals+' goals':'Goal'):gain('A')>0?'Assist':gain('W')>0?'Win':gain('SO')>0?'Shutout':'Points updated';
      const name=player.name.includes(',')?player.name.split(',').slice(1).join(',').trim()+' '+player.name.split(',')[0].trim():player.name;
      const teamTotal=Number(next.teamTotals?.[player.teamId] ?? next.players.filter(p=>String(p.teamId)===String(player.teamId)).reduce((sum,p)=>sum+p.points,0));
      return [{key:player.key, team:player.team, teamId:player.teamId, player:action+' by '+name+' ('+player.team+')', gameDisplay:player.gameDisplay||'', teamTotal, points, stats:stats.join(' · ') || 'Points updated'}];
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
      const close = $('close'), trash = $('trash');
      const count = $('count'), scope = $('scope'), team = $('team');
      const storageKey = 'ecfhl-scoring-popup:' + (document.getElementById('communication-context')?.dataset.userId || 'guest');
      const filterKey='ecfhl-scoring-filters:'+(document.getElementById('communication-context')?.dataset.userId||'guest');
      let filters={};try{filters=JSON.parse(localStorage.getItem(filterKey)||'{}');}catch(_){}
      let saved;
      try { saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (_) {}
      let baseline = saved?.baseline || null, nhlBaseline = saved?.nhlBaseline || null;
      let history = Array.isArray(saved?.history) ? saved.history : [];
      let enabled = filters.enabled !== false;
      let mode = ['expanded','minimized'].includes(saved?.mode) ? saved.mode : 'closed';
      let maximized=false;
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
        try { sessionStorage.setItem(storageKey, JSON.stringify({baseline,nhlBaseline,history,enabled,mode,scope:scope.value,team:selectedTeam,teams:selectedTeams})); } catch (_) {}
      };
      const render = () => {
        const total = history.reduce((sum,batch)=>sum+batch.events.length,0);
        const unread = history.reduce((sum,batch)=>sum+(batch.unread !== false ? batch.events.length : 0),0);
        tray.hidden = mode === 'closed'; tray.dataset.minimized = String(mode === 'minimized');tray.dataset.maximized=String(maximized);
        trash.disabled = total === 0; count.textContent = unread > 0 ? String(unread) : ''; count.hidden = unread === 0;
        count.dataset.active = String(unread > 0);
        toggle.dataset.enabled = String(enabled); toggle.setAttribute('aria-expanded',String(mode !== 'closed'));
        toggle.setAttribute('aria-label','Open scoring updates, '+unread+' unread, '+total+' stored, updates '+(enabled?'on':'off'));
        $('team-label').hidden = scope.value !== 'teams';
        $('team-summary').textContent = selectedTeams.length ? selectedTeams.length + ' selected' : 'Select teams';
        list.replaceChildren();
        if (!total) list.append(element('p','live-score-updates-empty','No updates'));
        history.forEach(batch => {
          const node = element('div','live-score-update-batch');
          node.append(element('time','live-score-update-time',batch.time));
          const teams = new Map(); batch.events.forEach(event=>{if(!teams.has(event.team))teams.set(event.team,[]);teams.get(event.team).push(event);});
          teams.forEach((plays,name)=>{
            const group = element('div','live-score-update-team'), heading = element('div','live-score-update-team-heading');
            if (batch.nhl && plays[0].game) {
              const game=plays[0].game;
              heading.append(element('strong',game.scoringTeam===game.team?'scoring-team-highlight':'',game.team+' '+game.score),element('span','',game.home?' vs ':' @ '),element('strong',game.scoringTeam===game.opponent?'scoring-team-highlight':'',game.opponent+' '+game.opponentScore));
            } else heading.append(element('strong','',name));
            if (!batch.nhl) {
              const total=plays[0].teamTotal;
              if (Number.isFinite(total)) heading.append(element('span','live-score-update-points',formatPoints(total)+' Fpts'));
            }
            group.append(heading);
            plays.forEach(play=>{
              const row=element('div','live-score-update-player'), detail=element('div','');
              row.setAttribute('data-new',String(Boolean(batch.fresh || batch.unread !== false)));
              if(batch.fresh || batch.unread !== false) detail.append(element('span','live-score-update-new','New'));
              detail.append(element('strong','',batch.nhl?'Goal by '+play.player:play.player));
              if (play.gameDisplay) detail.append(element('span','live-score-update-stats',play.gameDisplay));
              detail.append(element('span','live-score-update-stats',play.stats));row.append(detail);
              if (!batch.nhl) row.append(element('span','live-score-update-player-points','+'+formatPoints(play.points)));
              group.append(row);
            });node.append(group);
          });list.append(node);
        });
        persist();
      };
      // Opening the box never changes whether updates are received.
      window.addEventListener('ecfhl-panel-open',event=>{if(event.detail!=='scoring'&&mode!=='closed'){mode='closed';render();}});
      toggle.addEventListener('click',()=>{window.dispatchEvent(new CustomEvent('ecfhl-panel-open',{detail:'scoring'}));history.forEach(batch=>{batch.fresh=batch.unread !== false;batch.unread=false;});mode='expanded';render();close.focus({preventScroll:true});});
      $('minimize')?.addEventListener('click',()=>{mode=mode==='minimized'?'expanded':'minimized';if(mode==='minimized')maximized=false;render();});
      $('maximize')?.addEventListener('click',()=>{maximized=!maximized;mode='expanded';render();});
      trash.addEventListener('click',()=>{history=[];render();});
      close.addEventListener('click',()=>{history.forEach(batch=>{batch.fresh=false;});mode='closed';render();toggle.focus({preventScroll:true});});
      tray.addEventListener('keydown',event=>{if(event.key==='Escape'){history.forEach(batch=>{batch.fresh=false;});mode='closed';render();toggle.focus({preventScroll:true});}});
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
          if (Array.isArray(current)) {
            const latest = new Map(current.map(event => [event.key, event]));
            history.forEach(batch => {
              if (batch.nhl) batch.events = batch.events.map(event => latest.get(event.key) || event);
            });
          }
          events=Array.isArray(current)&&nhlBaseline?current.filter(e=>!nhlBaseline.includes(e.key)):[];
          if(Array.isArray(current))nhlBaseline=current.map(e=>e.key);
        }else nhlBaseline=null;
        if(enabled && events.length){
          history.unshift({time:new Date().toLocaleTimeString([],{hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true}),events,nhl:scope.value==='nhl',unread:mode !== 'expanded',fresh:mode === 'expanded'});
          // Retain the most recent 250 batches per tab to keep navigation state bounded.
          history=history.slice(0,250);
        }
        render();
      };
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

