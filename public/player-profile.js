(() => {
  const dialog=document.getElementById('player-stats-dialog');
  const content=document.getElementById('player-stats-content');
  if(!dialog||!content) return;
  let request=null;
  let trigger=null;
  const close=()=>dialog.close();
  dialog.querySelector('.player-stats-close').addEventListener('click',close);
  dialog.addEventListener('click',event=>{if(event.target===dialog){const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)close();}});
  dialog.addEventListener('close',()=>{
    // A queued close from the previous popup must not cancel a quick reopen.
    if(dialog.open)return;
    request?.abort();request=null;document.body.style.removeProperty('overflow');trigger?.focus({preventScroll:true});
  });
  document.addEventListener('click',async event=>{
    const link=event.target.closest('[data-player-stats]');
    if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey) return;
    event.preventDefault();
    request?.abort();request=new AbortController();const current=request;
    trigger=link;content.innerHTML='<p class="player-stats-loading" role="status">Loading player stats…</p>';
    dialog.showModal();document.body.style.overflow='hidden';
    try{
      const response=await fetch(link.href,{headers:{Accept:'application/json'},credentials:'same-origin',signal:current.signal});
      if(!response.ok)throw new Error('Could not load stats');
      const data=await response.json();
      if(typeof data.html!=='string')throw new Error('Invalid response');
      if(request===current&&dialog.open)content.innerHTML=data.html;
    }catch(error){
      if(error.name==='AbortError')return;
      if(request!==current)return;
      content.innerHTML='<p class="player-stats-error">Could not load player stats. <a>Open player page →</a></p>';
      content.querySelector('a').href=link.href;
    }
  });
})();
