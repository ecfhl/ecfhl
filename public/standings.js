(() => {
  let refreshing=false;
  const refresh=async()=>{
    const current=document.querySelector('.standings-page');
    if(!current||document.hidden||refreshing||document.querySelector('#team-icon-modal.open, dialog[open]'))return;
    refreshing=true;
    const controller=new AbortController();
    const timeout=setTimeout(()=>controller.abort(),20000);
    try{
      const response=await fetch(window.location.href,{
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},cache:'no-store',signal:controller.signal
      });
      if(!response.ok)throw new Error('Standings refresh failed');
      const doc=new DOMParser().parseFromString(await response.text(),'text/html');
      const fresh=doc.querySelector('.standings-page');
      if(!fresh)throw new Error('Missing standings');
      if(document.hidden||document.querySelector('#team-icon-modal.open, dialog[open]'))return;
      // Capture state after fetching so a click during a slow request is preserved.
      const states=new Map([...current.querySelectorAll('.standings-period')].map(el=>[el.dataset.periodNumber,el.open]));
      fresh.querySelectorAll('.standings-period').forEach(el=>{if(states.has(el.dataset.periodNumber))el.open=states.get(el.dataset.periodNumber)});
      const scrolls=[...current.querySelectorAll('.table-scroll')].map(el=>({left:el.scrollLeft,top:el.scrollTop}));
      const focused=document.activeElement;
      const link=focused?.closest('a[href],button[data-team-icon-viewer]');
      const focusKey=link&&current.contains(link)?{href:link.getAttribute('href'),slug:link.dataset.teamSlug}:null;
      const x=window.scrollX,y=window.scrollY;
      current.replaceWith(fresh);
      fresh.querySelectorAll('.table-scroll').forEach((el,i)=>{if(scrolls[i]){el.scrollLeft=scrolls[i].left;el.scrollTop=scrolls[i].top}});
      if(focusKey){
        const replacement=[...fresh.querySelectorAll('a[href],button[data-team-icon-viewer]')].find(el=>focusKey.slug?el.dataset.teamSlug===focusKey.slug:el.getAttribute('href')===focusKey.href);
        replacement?.focus({preventScroll:true});
      }
      window.scrollTo(x,y);
    }catch(error){
      const status=current.querySelector('.standings-refresh-status');
      if(status)status.textContent='Refresh delayed. Showing the last saved standings; retrying automatically.';
    }finally{
      clearTimeout(timeout);
      refreshing=false;
    }
  };
  setInterval(refresh,60000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh()});
})();
