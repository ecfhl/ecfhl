(()=>{
 const user=document.getElementById('communication-context')?.dataset.userId||'guest';
 document.querySelectorAll('#chat-panel').forEach(panel=>{
  const key='ecfhl-panel-size:'+user+':'+panel.id;let size=null,drag=null;
  try{const value=JSON.parse(sessionStorage.getItem(key)||'null');if(value&&Number.isFinite(value.width)&&Number.isFinite(value.height))size=value;}catch(_){}
  const handle=document.createElement('button');handle.type='button';handle.className='panel-resize-handle';handle.setAttribute('aria-label','Resize '+panel.getAttribute('aria-label')+': drag or use arrow keys');handle.title='Resize: drag or use arrow keys';panel.append(handle);panel.dataset.resizablePanel='true';
  const minimized=()=>panel.dataset.minimized==='true';
  const minimizeButton=panel.querySelector('button[id$="-minimize"]'),restoreButton=panel.querySelector('.panel-header-restore'),restoreRow=panel.querySelector('.notification-restore,.live-score-updates-restore');
  restoreButton?.addEventListener('click',()=>restoreRow?.click());
  const controls=()=>{if(restoreButton)restoreButton.disabled=!minimized();if(minimizeButton)minimizeButton.disabled=minimized();};
  const collapsedHeight=()=>panel.querySelector('.communication-panel-heading,.live-score-updates-heading').getBoundingClientRect().height+36;

  const notify=position=>panel.dispatchEvent(new CustomEvent('ecfhl-panel-resize',{detail:position?{position}:{}}));
  const persist=()=>{if(size)try{sessionStorage.setItem(key,JSON.stringify(size));}catch(_){}};
  const apply=()=>{
   controls();if(panel.dataset.maximized==='true')return;if(!size||panel.hidden)return;
   if(!minimized()&&size.height<=collapsedHeight()){size.height=drag?.height||300;minimizeButton?.click();return;}
   const rect=panel.getBoundingClientRect(),nav=document.querySelector('.mobile-primary-nav');
   const bottom=nav&&getComputedStyle(nav).display!=='none'?nav.getBoundingClientRect().top:window.innerHeight;
   const availableWidth=Math.max(1,window.innerWidth-Math.max(8,rect.left)-8),availableHeight=Math.max(1,bottom-Math.max(8,rect.top)-8);
   panel.style.width=Math.min(Math.max(280,size.width),availableWidth)+'px';
   panel.style.height=minimized()?'':Math.min(Math.max(collapsedHeight(),size.height),availableHeight)+'px';
   panel.dataset.customSize='true';notify();
  };
  handle.addEventListener('pointerdown',event=>{
   if(event.button!==0)return;event.preventDefault();event.stopPropagation();const rect=panel.getBoundingClientRect();
   if(!size)size={width:rect.width,height:minimized()?300:rect.height};
   drag={id:event.pointerId,x:event.clientX,y:event.clientY,width:rect.width,height:rect.height};
   notify({x:rect.left,y:rect.top});handle.setPointerCapture(event.pointerId);panel.dataset.resizing='true';
  });
  handle.addEventListener('pointermove',event=>{
   if(!drag||event.pointerId!==drag.id)return;
   size.width=drag.width+event.clientX-drag.x;if(!minimized())size.height=drag.height+event.clientY-drag.y;apply();
  });
  const end=event=>{
   if(!drag||event.pointerId!==drag.id)return;if(handle.hasPointerCapture(event.pointerId))handle.releasePointerCapture(event.pointerId);drag=null;panel.dataset.resizing='false';
   const rect=panel.getBoundingClientRect();size.width=rect.width;if(!minimized())size.height=rect.height;persist();
  };
  ['pointerup','pointercancel','lostpointercapture'].forEach(type=>handle.addEventListener(type,end));
  handle.addEventListener('keydown',event=>{
   if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();event.stopPropagation();
   const rect=panel.getBoundingClientRect(),step=event.shiftKey?30:10;if(!size)size={width:rect.width,height:minimized()?300:rect.height};
   notify({x:rect.left,y:rect.top});
   size.width=rect.width+(event.key==='ArrowLeft'?-step:event.key==='ArrowRight'?step:0);
   if(!minimized())size.height=rect.height+(event.key==='ArrowUp'?-step:event.key==='ArrowDown'?step:0);
   apply();const updated=panel.getBoundingClientRect();size.width=updated.width;if(!minimized())size.height=updated.height;persist();
  });
  new MutationObserver(apply).observe(panel,{attributes:true,attributeFilter:['hidden','data-minimized','data-maximized']});
  window.addEventListener('resize',apply);apply();
 });
})();

