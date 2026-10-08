(() => {
 const context=document.getElementById('communication-context');
 const userId=Number(context?.dataset.userId||0);
 let preferences={};try{preferences=JSON.parse(context?.dataset.preferences||'{}');}catch(_){}
 const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.content||'';
 const request=async(url,body)=>{
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{
   const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}: {})},...(body?{method:'POST',body:JSON.stringify(body)}:{})});
   const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Please reload and try again.');return data;
  }finally{clearTimeout(timer);}
 };
 const node=(tag,text,className)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(className)n.className=className;return n;};
 const counter=(id,value)=>{const n=document.getElementById(id);if(n){n.textContent=String(value);n.dataset.active=String(value>0);}};
 const scoring=window.EcfhlScoreUpdates?.start();
 let scoreBusy=false,scorePending=false;
 const refreshScoring=async()=>{if(document.hidden)return;if(scoreBusy){scorePending=true;return;}scoreBusy=true;try{scoring?.update(await request('/api/scoring-updates?scope='+encodeURIComponent(scoring.scope())));}catch(_){/* Keep saved scores and retry next tick. */}finally{scoreBusy=false;if(scorePending){scorePending=false;refreshScoring();}}};
 refreshScoring();setInterval(refreshScoring,60000);window.addEventListener('ecfhl-scoring-filter',refreshScoring);
 const notificationPanel=document.getElementById('notification-panel'),notificationToggle=document.getElementById('header-notifications-toggle');
 const notificationHandle=document.getElementById('notification-handle'),notificationClose=document.querySelector('[data-close-notifications]'),notificationTrash=document.getElementById('notification-trash'),notificationRestore=document.getElementById('notification-restore');
 const notificationKey='ecfhl-notification-panel:'+(userId||'guest');
 let savedNotifications={};try{savedNotifications=JSON.parse(sessionStorage.getItem(notificationKey)||'{}')||{};}catch(_){}
 let notificationMode=['closed','expanded','minimized'].includes(savedNotifications.mode)?savedNotifications.mode:'closed',notificationPosition=savedNotifications.position||null,notificationDrag=null,notificationTotal=0;
 let clearedNotifications=new Set(Array.isArray(savedNotifications.cleared)?savedNotifications.cleared.map(Number):[]);
 const persistNotifications=()=>{try{sessionStorage.setItem(notificationKey,JSON.stringify({mode:notificationMode,position:notificationPosition,cleared:[...clearedNotifications].slice(-1000)}));}catch(_){}};
 const constrainNotifications=()=>{
  if(!notificationPosition||notificationPanel.hidden)return;
  const rect=notificationPanel.getBoundingClientRect(),nav=document.querySelector('.mobile-primary-nav');
  const bottom=nav&&getComputedStyle(nav).display!=='none'?nav.getBoundingClientRect().top:window.innerHeight;
  notificationPosition.x=Math.max(8,Math.min(notificationPosition.x,window.innerWidth-rect.width-8));
  notificationPosition.y=Math.max(8,Math.min(notificationPosition.y,bottom-rect.height-8));
  Object.assign(notificationPanel.style,{left:notificationPosition.x+'px',top:notificationPosition.y+'px',right:'auto'});
 };
 const renderNotifications=()=>{
  notificationPanel.hidden=notificationMode==='closed';notificationPanel.dataset.minimized=String(notificationMode==='minimized');
  notificationRestore.hidden=notificationMode!=='minimized';notificationRestore.textContent=notificationTotal+' notifications';
  notificationTrash.disabled=notificationTotal===0;notificationToggle.setAttribute('aria-expanded',String(notificationMode!=='closed'));
  constrainNotifications();persistNotifications();
 };
 notificationToggle.addEventListener('click',()=>{notificationMode='expanded';renderNotifications();notificationClose.focus({preventScroll:true});});
 const closeNotifications=()=>{notificationMode='closed';renderNotifications();notificationToggle.focus({preventScroll:true});};
 notificationClose.addEventListener('click',closeNotifications);
 document.getElementById('notification-minimize').addEventListener('click',()=>{notificationMode='minimized';renderNotifications();notificationRestore.focus({preventScroll:true});});
 notificationRestore.addEventListener('click',()=>{notificationMode='expanded';renderNotifications();notificationClose.focus({preventScroll:true});});
 notificationPanel.addEventListener('keydown',event=>{if(event.key==='Escape')closeNotifications();});
 notificationHandle.addEventListener('pointerdown',event=>{
  if(event.button!==0||event.target.closest('button,a,select,input,label'))return;
  const rect=notificationPanel.getBoundingClientRect();notificationDrag={id:event.pointerId,x:event.clientX,y:event.clientY,left:rect.left,top:rect.top};
  notificationHandle.setPointerCapture(event.pointerId);notificationPanel.dataset.dragging='true';
 });
 notificationHandle.addEventListener('pointermove',event=>{if(!notificationDrag||notificationDrag.id!==event.pointerId)return;notificationPosition={x:notificationDrag.left+event.clientX-notificationDrag.x,y:notificationDrag.top+event.clientY-notificationDrag.y};constrainNotifications();});
 const endNotificationDrag=event=>{if(!notificationDrag||notificationDrag.id!==event.pointerId)return;if(notificationHandle.hasPointerCapture(event.pointerId))notificationHandle.releasePointerCapture(event.pointerId);notificationDrag=null;notificationPanel.dataset.dragging='false';persistNotifications();};
 ['pointerup','pointercancel','lostpointercapture'].forEach(type=>notificationHandle.addEventListener(type,endNotificationDrag));
 notificationHandle.addEventListener('keydown',event=>{
  if(event.target!==notificationHandle||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
  event.preventDefault();const rect=notificationPanel.getBoundingClientRect(),step=event.shiftKey?30:10;
  notificationPosition={x:rect.left+(event.key==='ArrowLeft'?-step:event.key==='ArrowRight'?step:0),y:rect.top+(event.key==='ArrowUp'?-step:event.key==='ArrowDown'?step:0)};constrainNotifications();persistNotifications();
 });
 window.addEventListener('resize',()=>{constrainNotifications();persistNotifications();});renderNotifications();
 if(!userId){document.getElementById('header-notification-status').dataset.enabled='false';document.getElementById('header-notification-status').setAttribute('aria-label','Sign in for notifications');document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshScoring();});return;}
 let owners={},inbox=[],stateBusy=false,notificationStateReady=false;
 const popupCursorKey='ecfhl-message-cursor:'+userId;
 let cursor=null;try{const value=sessionStorage.getItem(popupCursorKey);if(value!==null)cursor=Number(value);}catch(_){}
 const setCursor=value=>{cursor=value;try{sessionStorage.setItem(popupCursorKey,String(value));}catch(_){}};
 const savePreferences=async patch=>{const saved=await request('/api/communication/preferences',patch);preferences=saved.preferences;syncPreferences();};
 const enabledSwitch=document.getElementById('header-notifications-enabled');
 const syncPreferences=()=>{
  if(enabledSwitch)enabledSwitch.checked=preferences.notifications_enabled!==false;
  const notificationsEnabled=preferences.notifications_enabled!==false;
  const dot=document.getElementById('header-notification-status');dot.dataset.enabled=String(notificationsEnabled);dot.setAttribute('aria-label','Notifications '+(notificationsEnabled?'on':'off'));notificationToggle.title='Notifications '+(notificationsEnabled?'on':'off');
  document.querySelectorAll('[data-message-preference]').forEach(input=>input.checked=preferences[input.dataset.messagePreference]!==false);
 };
 syncPreferences();
 enabledSwitch?.addEventListener('change',async()=>{enabledSwitch.disabled=true;try{await savePreferences({notifications_enabled:enabledSwitch.checked});}catch(e){document.getElementById('header-push-state').textContent=e.message;syncPreferences();}finally{enabledSwitch.disabled=false;}});
 document.querySelectorAll('[data-message-preference]').forEach(input=>input.addEventListener('change',async()=>{input.disabled=true;const status=document.getElementById('message-preferences-status');try{await savePreferences({[input.dataset.messagePreference]:input.checked});status.textContent='Saved.';}catch(e){status.textContent=e.message;syncPreferences();}finally{input.disabled=false;}}));
 const teamMessage=()=>{const modal=document.getElementById('team-icon-modal'),link=document.getElementById('team-icon-modal-message');if(!link)return;const owner=owners[modal?.dataset.messageSlug];link.hidden=!owner;if(owner)link.href='/messages?user_id='+owner;else link.removeAttribute('href');};
 window.addEventListener('ecfhl-team-viewer',teamMessage);
 const safeUrl=value=>{try{const url=new URL(value||'/notifications',location.origin);return ['http:','https:'].includes(url.protocol)?url.href:'/notifications';}catch(_){return '/notifications';}};
 const drawInbox=()=>{
  const list=document.getElementById('notification-inbox');list.replaceChildren();
  if(!inbox.length)list.append(node('p','No notifications yet.'));
  const visibleInbox=inbox.filter(item=>!clearedNotifications.has(Number(item.id)));
  if(inbox.length&&!visibleInbox.length)list.append(node('p','No notifications yet.'));
  visibleInbox.forEach(item=>{const entry=node('div',undefined,'notification-entry');entry.dataset.unread=String(!item.read_at);const link=node('a',item.title);link.href=safeUrl(item.url);link.addEventListener('click',async event=>{if(event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();try{await request('/api/notifications/read',{ids:[item.id]});}finally{location.assign(link.href);}});entry.append(link,node('p',item.body));list.append(entry);});
  notificationTotal=visibleInbox.length;renderNotifications();
 };
 notificationTrash.addEventListener('click',async()=>{
  const items=inbox.filter(item=>!clearedNotifications.has(Number(item.id))),status=document.getElementById('notification-panel-status');notificationTrash.disabled=true;
  try{
   const ids=items.filter(item=>!item.read_at).map(item=>item.id);if(ids.length)await request('/api/notifications/read',{ids});
   items.forEach(item=>{clearedNotifications.add(Number(item.id));item.read_at=item.read_at||'read';});
   if(status){status.hidden=true;status.textContent='';}
   notificationMode='minimized';drawInbox();counter('header-notification-count',inbox.filter(item=>!item.read_at).length);notificationRestore.focus({preventScroll:true});await refreshState();
  }catch(e){notificationMode='expanded';renderNotifications();if(status){status.hidden=false;status.textContent=e.message;}}
 });
 const widgets=[...document.querySelectorAll('[data-chat-widget]')].map(element=>({element,other:element.dataset.other?Number(element.dataset.other):null,log:element.querySelector('.chat-log'),status:element.querySelector('.chat-status'),messages:new Map(),busy:false,lastRead:0}));
 const visible=widget=>{if(document.hidden)return false;const rect=widget.log.getBoundingClientRect();return rect.bottom>0&&rect.top<window.innerHeight&&rect.width>0;};
 const atBottom=widget=>widget.log.scrollHeight-widget.log.scrollTop-widget.log.clientHeight<40;
 const updateUnread=value=>counter('header-message-count',value.total);
 const readWidget=async widget=>{
  if(!visible(widget)||!atBottom(widget)||widget.readBusy)return;
  const ids=[...widget.messages.keys()],id=ids.length?Math.max(...ids):0;if(id<=widget.lastRead)return;
  widget.readBusy=true;try{const data=await request('/api/messages/read',{user_id:widget.other,last_id:id});widget.lastRead=id;updateUnread(data.unread);}catch(_){}finally{widget.readBusy=false;}
 };
 const drawWidget=(widget,newMessages,older=false)=>{
  const bottom=atBottom(widget),height=widget.log.scrollHeight;
  const added=newMessages.filter(m=>!widget.messages.has(Number(m.id)));added.forEach(m=>widget.messages.set(Number(m.id),m));
  if(!added.length)return;
  widget.log.replaceChildren();
  [...widget.messages.values()].sort((a,b)=>a.id-b.id).forEach(message=>{
   const row=node('article',undefined,'chat-message');row.dataset.own=String(Number(message.sender_id)===userId);
   const identity=node('div',undefined,'chat-message-identity'),logo=node('img');logo.src=message.team_logo||'/team-icons/league-logo/thumbnail?size=64';logo.alt='';logo.width=32;logo.height=32;logo.loading='lazy';identity.append(logo,node('strong',message.team_name||'League member'));row.append(identity,node('p',message.body));
   const time=node('time',new Date(message.created_at).toLocaleString());row.append(time);widget.log.append(row);
  });
  if(older)widget.log.scrollTop+=widget.log.scrollHeight-height;else if(bottom)widget.log.scrollTop=widget.log.scrollHeight;
  readWidget(widget);
 };
 const refreshWidget=async(widget,older=false)=>{
  if(widget.busy||document.hidden)return;widget.busy=true;
  try{
   const ids=[...widget.messages.keys()],params=new URLSearchParams();if(widget.other)params.set('user_id',widget.other);
   if(ids.length)params.set(older?'before':'after',String(older?Math.min(...ids):Math.max(...ids)));
   const data=await request('/api/messages/conversation?'+params);drawWidget(widget,data.messages,older);
   if(older||!ids.length)widget.element.querySelector('.chat-older').hidden=!data.has_more;
   if(!widget.messages.size)widget.log.textContent='No messages yet. Start the conversation.';
   widget.status.textContent='';
  }catch(e){widget.status.textContent=e.message;}finally{widget.busy=false;}
 };
 widgets.forEach(widget=>{
  widget.element.querySelector('.chat-older').addEventListener('click',()=>refreshWidget(widget,true));
  widget.log.addEventListener('scroll',()=>readWidget(widget));
  const form=widget.element.querySelector('.chat-form'),text=form.querySelector('textarea'),send=form.querySelector('button');
  let pending=null;
  form.addEventListener('submit',async event=>{
   event.preventDefault();if(send.disabled||!text.value.trim())return;
   const body=text.value.trim();if(!pending||pending.body!==body)pending={body,client_id:crypto.randomUUID()};
   send.disabled=true;widget.status.textContent='Sending…';
   try{
    const data=await request('/api/messages/send',{...pending,user_id:widget.other});pending=null;text.value='';
    drawWidget(widget,[data.message]);widget.log.scrollTop=widget.log.scrollHeight;await readWidget(widget);widget.status.textContent='Sent.';refreshState();
   }catch(e){widget.status.textContent=e.message;}finally{send.disabled=false;}
  });refreshWidget(widget);
 });
 const showPopup=message=>{
  if(Number(message.sender_id)===userId)return;
  const league=message.recipient_id===null;
  if(preferences[league?'league_message_popups':'private_message_popups']===false)return;
  const open=widgets.find(w=>(league?w.other===null:w.other===Number(message.sender_id))&&visible(w)&&atBottom(w));if(open)return;
  const box=node('section',undefined,'message-popup'),heading=node('div',undefined,'message-popup-heading');
  const logo=node('img');logo.src=message.team_logo||'/team-icons/league-logo/thumbnail?size=64';logo.alt='';logo.width=32;logo.height=32;heading.append(logo,node('strong',(league?'League chat · ':'')+(message.team_name||'League member')));
  const close=node('button','×','message-popup-close');close.type='button';close.setAttribute('aria-label','Dismiss message popup');close.addEventListener('click',()=>box.remove());heading.append(close);
  const link=node('a','Open conversation');link.href=league?'/messages':'/messages?user_id='+message.sender_id;
  box.append(heading,node('p',message.body.slice(0,240)),link);
  const host=document.getElementById('message-popups');host.append(box);while(host.children.length>3)host.firstElementChild.remove();
 };
 const refreshState=async()=>{
  if(stateBusy||document.hidden)return;stateBusy=true;
  try{
   const data=await request('/api/messages/state'+(cursor!==null?'?after='+cursor:''));
   preferences=data.preferences;syncPreferences();owners=data.owners;teamMessage();const previousIds=new Set(inbox.map(item=>Number(item.id)));
   inbox=data.notifications;
   if(notificationStateReady&&inbox.some(item=>!item.read_at&&!clearedNotifications.has(Number(item.id))&&!previousIds.has(Number(item.id)))&&notificationMode==='closed')notificationMode='minimized';
   notificationStateReady=true;drawInbox();updateUnread(data.unread);counter('header-notification-count',data.notification_count);
   if(cursor===null)setCursor(data.latest_id);else{data.messages.forEach(showPopup);setCursor(data.messages.length?Number(data.messages[data.messages.length-1].id):Math.max(cursor,data.latest_id));}
  }catch(_){/* Preserve counters and retry. */}finally{stateBusy=false;}
 };
 // Browser permission is requested only from the user's enable-button click.
 const pushButton=document.getElementById('header-enable-push'),pushStatus=document.getElementById('header-push-state')||node('p');
 let deviceMuted=false;try{deviceMuted=localStorage.getItem('ecfhl-push-disabled:'+userId)==='1';}catch(_){}
 const pushSupported='serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;
 const b64=s=>Uint8Array.from(atob((s+'='.repeat((4-s.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));
 const connectPush=async()=>{
  await navigator.serviceWorker.register('/push-sw.js',{scope:'/'});const registration=await navigator.serviceWorker.ready;
  const device=await request('/push/device'),existing=await registration.pushManager.getSubscription();
  if(!device.enabled||!existing){
   const config=await request('/push/config');const subscription=existing||await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64(config.publicKey)});
   const saved=await request('/push/subscribe',{endpoint:subscription.endpoint});
   await new Promise((resolve,reject)=>{const channel=new MessageChannel(),timer=setTimeout(()=>reject(new Error('Reload and enable notifications again.')),10000);channel.port1.onmessage=()=>{clearTimeout(timer);resolve();};registration.active.postMessage({type:'set-owner-feed',token:saved.feedToken,lastId:saved.latestId},[channel.port2]);});
  }
  pushStatus.textContent='Browser push notifications enabled.';if(pushButton)pushButton.hidden=true;
 };
 if(!pushSupported){if(pushButton)pushButton.disabled=true;pushStatus.textContent='Browser push is unavailable here. Message popups still work.';}
 else if(Notification.permission==='granted'&&!deviceMuted)connectPush().catch(e=>pushStatus.textContent=e.message);
 else pushStatus.textContent=Notification.permission==='denied'?'Notifications are blocked in this browser. Allow them in browser settings.':'Messages are enabled. Allow browser notifications to receive push alerts.';
 pushButton?.addEventListener('click',async()=>{pushButton.disabled=true;try{if(await Notification.requestPermission()!=='granted')throw new Error('Allow notifications in your browser to receive push alerts.');try{localStorage.removeItem('ecfhl-push-disabled:'+userId);}catch(_){}await connectPush();}catch(e){pushStatus.textContent=e.message;}finally{if(pushButton)pushButton.disabled=false;}});
 refreshState();setInterval(()=>{refreshState();widgets.forEach(w=>refreshWidget(w));},15000);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden){refreshScoring();refreshState();widgets.forEach(w=>refreshWidget(w));}});
 window.addEventListener('scroll',()=>widgets.forEach(readWidget),{passive:true});
})();
