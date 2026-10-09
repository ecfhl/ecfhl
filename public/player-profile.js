(() => {
  const dialog=document.getElementById('player-stats-dialog');
  const content=document.getElementById('player-stats-content');
  if(!dialog||!content) return;
  let request=null;
  let trigger=null;
  const close=()=>dialog.close();
  dialog.querySelector('.player-stats-close').addEventListener('click',close);
  document.addEventListener('pointerdown',event=>{
    if(dialog.open&&!dialog.contains(event.target))close();
  });
  dialog.addEventListener('close',()=>{
    // A queued close from the previous popup must not cancel a quick reopen.
    if(dialog.open)return;
    request?.abort();request=null;trigger?.focus({preventScroll:true});
  });
  let drag=null;
  dialog.addEventListener('pointerdown',event=>{
    const header=event.target.closest('.player-profile header');
    if(!header||event.target.closest('a,button')||event.button!==0)return;
    event.preventDefault();const box=dialog.getBoundingClientRect();
    dialog.style.left=box.left+'px';dialog.style.top=box.top+'px';dialog.style.transform='none';
    drag={id:event.pointerId,x:event.clientX,y:event.clientY,left:box.left,top:box.top};
    dialog.setPointerCapture(event.pointerId);
  });
  dialog.addEventListener('pointermove',event=>{
    if(!drag||event.pointerId!==drag.id)return;
    const box=dialog.getBoundingClientRect();
    dialog.style.left=Math.max(0,Math.min(window.innerWidth-box.width,drag.left+event.clientX-drag.x))+'px';
    dialog.style.top=Math.max(0,Math.min(window.innerHeight-44,drag.top+event.clientY-drag.y))+'px';
  });
  const endDrag=event=>{if(drag&&event.pointerId===drag.id){drag=null;if(dialog.hasPointerCapture(event.pointerId))dialog.releasePointerCapture(event.pointerId);}};
  ['pointerup','pointercancel','lostpointercapture'].forEach(type=>dialog.addEventListener(type,endDrag));
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&dialog.open){event.preventDefault();close();}});
  window.addEventListener('resize',()=>{
    if(!dialog.open||!dialog.style.left)return;const box=dialog.getBoundingClientRect();
    dialog.style.left=Math.max(0,Math.min(box.left,window.innerWidth-box.width))+'px';
    dialog.style.top=Math.max(0,Math.min(box.top,window.innerHeight-44))+'px';
  });
  document.addEventListener('click',async event=>{
    const link=event.target.closest('[data-player-stats]');
    if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey) return;
    event.preventDefault();
    request?.abort();request=new AbortController();const current=request;
    trigger=link;content.innerHTML='<p class="player-stats-loading" role="status">Loading player stats…</p>';
    if(!dialog.open)dialog.show();
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
