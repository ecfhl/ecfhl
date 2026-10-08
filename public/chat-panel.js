(()=>{
 const panel=document.getElementById('chat-panel');if(!panel)return;
 const userId=Number(document.getElementById('communication-context')?.dataset.userId||0);
 const $=id=>document.getElementById('chat-panel-'+id),handle=$('handle'),close=$('close'),restore=$('restore'),select=$('conversation'),log=$('log'),form=$('form'),text=$('text'),send=form.querySelector('button'),status=$('status'),older=$('older');
 const header=document.querySelector('.header-actions a[href="/messages"]'),key='ecfhl-chat-panel:'+userId;
 let saved={};try{saved=JSON.parse(sessionStorage.getItem(key)||'{}')||{};}catch(_){}
 let mode=['closed','expanded','minimized'].includes(saved.mode)?saved.mode:'closed',position=saved.position||null,drag=null,selected='',teams=[],directoryReady=false,generation=0,sending=false;
 const conversations=new Map();
 const conversation=value=>{if(!conversations.has(value))conversations.set(value,{messages:new Map(),draft:'',pending:null,lastRead:0,hasMore:false,busy:false,readBusy:false});return conversations.get(value);};
 const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.content||'';
 const request=async(url,body)=>{
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{const r=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}: {})},...(body?{method:'POST',body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Please try again.');return d;}finally{clearTimeout(timer);}
 };
 const title=()=>selected?(teams.find(t=>String(t.user_id)===selected)?.team_name||'Team messages'):'League chat';
 const persist=()=>{try{sessionStorage.setItem(key,JSON.stringify({mode,position,selected}));}catch(_){}};
 const constrain=()=>{
  if(!position||panel.hidden)return;const rect=panel.getBoundingClientRect(),nav=document.querySelector('.mobile-primary-nav'),bottom=nav&&getComputedStyle(nav).display!=='none'?nav.getBoundingClientRect().top:window.innerHeight;
  position.x=Math.max(8,Math.min(position.x,window.innerWidth-rect.width-8));position.y=Math.max(8,Math.min(position.y,bottom-rect.height-8));Object.assign(panel.style,{left:position.x+'px',top:position.y+'px',right:'auto'});
 };
 const render=()=>{panel.hidden=mode==='closed';panel.dataset.minimized=String(mode==='minimized');restore.hidden=mode!=='minimized';restore.textContent=title();header?.setAttribute('aria-expanded',String(mode!=='closed'));constrain();persist();};
 const atBottom=()=>log.scrollHeight-log.scrollTop-log.clientHeight<40;
 const isReading=message=>mode==='expanded'&&!document.hidden&&atBottom()&&(message.recipient_id===null?selected==='':selected===String(message.sender_id));
 const counter=value=>{const el=document.getElementById('header-message-count');if(el){el.textContent=value>0?String(value):'';el.hidden=!(value>0);el.dataset.active=String(value>0);}};
 const read=async()=>{
  const value=selected,state=conversation(value);if(mode!=='expanded'||document.hidden||!atBottom()||state.readBusy)return;
  const ids=[...state.messages.keys()],id=ids.length?Math.max(...ids):0;if(id<=state.lastRead)return;state.readBusy=true;
  try{const d=await request('/api/messages/read',{user_id:value?Number(value):null,last_id:id});state.lastRead=Math.max(state.lastRead,id);counter(d.unread.total);}catch(_){}finally{state.readBusy=false;}
 };
 const node=(tag,value,className)=>{const el=document.createElement(tag);if(value!==undefined)el.textContent=value;if(className)el.className=className;return el;};
 const draw=(oldPage=false)=>{
  const state=conversation(selected),bottom=atBottom(),height=log.scrollHeight;log.replaceChildren();
  if(!state.messages.size)log.append(node('p','No messages yet. Start the conversation.'));
  [...state.messages.values()].sort((a,b)=>a.id-b.id).forEach(message=>{
   const row=node('article',undefined,'chat-message');row.dataset.own=String(Number(message.sender_id)===userId);
   const identity=node('div',undefined,'chat-message-identity'),logo=node('img');logo.src=message.team_logo||'/team-icons/league-logo/thumbnail?size=64';logo.alt='';logo.width=32;logo.height=32;identity.append(logo,node('strong',message.team_name||'League member'));
   row.append(identity,node('p',message.body),node('time',new Date(message.created_at).toLocaleString([],{timeZone:'America/Halifax',hour12:true})));log.append(row);
  });
  older.hidden=!state.hasMore;if(oldPage)log.scrollTop+=log.scrollHeight-height;else if(bottom)log.scrollTop=log.scrollHeight;constrain();read();
 };
 const refresh=async(oldPage=false)=>{
  const value=selected,state=conversation(value),version=generation;if(mode!=='expanded'||document.hidden||state.busy||!directoryReady)return;state.busy=true;
  const ids=[...state.messages.keys()],params=new URLSearchParams();if(value)params.set('user_id',value);if(ids.length)params.set(oldPage?'before':'after',String(oldPage?Math.min(...ids):Math.max(...ids)));
  try{const d=await request('/api/messages/conversation?'+params);d.messages.forEach(m=>state.messages.set(Number(m.id),m));if(oldPage||!ids.length)state.hasMore=d.has_more;if(version===generation&&value===selected){status.textContent='';draw(oldPage);}}catch(e){if(version===generation)status.textContent=e.message;}finally{state.busy=false;}
 };
 const change=value=>{
  value=String(value||'');if(value&&!teams.some(t=>String(t.user_id)===value))value='';
  conversation(selected).draft=text.value;selected=value;generation++;select.value=value;text.value=conversation(value).draft;status.textContent='';log.replaceChildren();draw();log.scrollTop=log.scrollHeight;render();refresh();
 };
 let requested=new URL(location.href).pathname==='/messages'?new URL(location.href).searchParams.get('user_id'):null;
 let pendingOpen=null;
 const open=value=>{
  if(!directoryReady){pendingOpen=String(value||'');mode='expanded';render();return;}
  mode='expanded';change(value);close.focus({preventScroll:true});
 };
 const updateDirectory=data=>{
  if(!Array.isArray(data.teams))return;
  teams=data.teams.filter(t=>Number(t.user_id)!==userId);
  select.replaceChildren(node('option','League chat'));select.firstElementChild.value='';
  teams.forEach(t=>{const option=node('option',t.team_name);option.value=String(t.user_id);select.append(option);});
  if(!directoryReady){
   directoryReady=true;const initial=pendingOpen!==null?pendingOpen:requested||saved.selected||'';if(requested!==null||location.pathname==='/messages')mode='expanded';change(initial);pendingOpen=null;
  }else if(selected&&!teams.some(t=>String(t.user_id)===selected))change('');else select.value=selected;
 };
 window.addEventListener('ecfhl-message-state',event=>updateDirectory(event.detail));
 document.addEventListener('click',event=>{
  const link=event.target.closest('a[href]');if(!link||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  const url=new URL(link.href,location.origin);if(url.origin!==location.origin||url.pathname!=='/messages')return;event.preventDefault();
  if(link.id==='team-icon-modal-message')document.getElementById('team-icon-modal-close')?.click();
  open(url.searchParams.get('user_id')||'');
 },true);
 select.addEventListener('change',()=>change(select.value));
 text.addEventListener('input',()=>{conversation(selected).draft=text.value;});
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(sending||!text.value.trim()||!directoryReady)return;
  const value=selected,state=conversation(value),body=text.value.trim();if(!state.pending||state.pending.body!==body)state.pending={body,client_id:crypto.randomUUID()};
  const attempt=state.pending;sending=true;send.disabled=true;status.textContent='Sending…';
  try{const d=await request('/api/messages/send',{...attempt,user_id:value?Number(value):null});state.pending=null;state.messages.set(Number(d.message.id),d.message);if(state.draft.trim()===body)state.draft='';
   if(value===selected){if(text.value.trim()===body)text.value='';draw();log.scrollTop=log.scrollHeight;await read();status.textContent='Sent.';}
  }catch(e){if(value===selected)status.textContent=e.message;}finally{sending=false;send.disabled=false;}
 });
 const hide=()=>{mode='closed';render();header?.focus({preventScroll:true});};
 close.addEventListener('click',hide);$('minimize').addEventListener('click',()=>{mode='minimized';render();restore.focus({preventScroll:true});});
 restore.addEventListener('click',()=>{mode='expanded';render();refresh();read();close.focus({preventScroll:true});});
 older.addEventListener('click',()=>refresh(true));log.addEventListener('scroll',read);
 panel.addEventListener('keydown',event=>{if(event.key==='Escape')hide();});
 handle.addEventListener('pointerdown',event=>{if(event.button!==0||event.target.closest('button,a,select,input,label'))return;const rect=panel.getBoundingClientRect();drag={id:event.pointerId,x:event.clientX,y:event.clientY,left:rect.left,top:rect.top};handle.setPointerCapture(event.pointerId);panel.dataset.dragging='true';});
 handle.addEventListener('pointermove',event=>{if(!drag||event.pointerId!==drag.id)return;position={x:drag.left+event.clientX-drag.x,y:drag.top+event.clientY-drag.y};constrain();});
 const end=event=>{if(!drag||event.pointerId!==drag.id)return;if(handle.hasPointerCapture(event.pointerId))handle.releasePointerCapture(event.pointerId);drag=null;panel.dataset.dragging='false';persist();};
 ['pointerup','pointercancel','lostpointercapture'].forEach(type=>handle.addEventListener(type,end));
 handle.addEventListener('keydown',event=>{if(event.target!==handle||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const rect=panel.getBoundingClientRect(),step=event.shiftKey?30:10;position={x:rect.left+(event.key==='ArrowLeft'?-step:event.key==='ArrowRight'?step:0),y:rect.top+(event.key==='ArrowUp'?-step:event.key==='ArrowDown'?step:0)};constrain();persist();});
 window.addEventListener('resize',()=>{constrain();persist();});document.addEventListener('visibilitychange',()=>{if(!document.hidden){refresh();read();}});
 window.EcfhlChatPanel={open,isReading};render();request('/api/messages/state').then(updateDirectory).catch(e=>status.textContent=e.message);setInterval(()=>refresh(),15000);
})();
