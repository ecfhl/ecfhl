(()=>{
 const panel=document.getElementById('chat-panel');if(!panel)return;
 const userId=Number(document.getElementById('communication-context')?.dataset.userId||0);
 const $=id=>document.getElementById('chat-panel-'+id),handle=$('handle'),close=$('close'),restore=$('restore'),select=$('conversation'),log=$('log'),form=$('form'),text=$('text'),send=form.querySelector('[type=submit]'),status=$('status'),older=$('older');
 const header=document.querySelector('.header-actions a[href="/messages"]'),key='ecfhl-chat-panel:'+userId;
 let saved={};try{saved=JSON.parse(sessionStorage.getItem(key)||'{}')||{};}catch(_){}
 let mode=['closed','expanded','minimized'].includes(saved.mode)?saved.mode:'closed',position=saved.position||null,drag=null,selected='',teams=[],directoryReady=false,generation=0,sending=false;
 let maximized=false,unread={total:0,league:0,people:{}};
 const maximize=$('maximize');
 const conversations=new Map();
 const conversation=value=>{if(!conversations.has(value))conversations.set(value,{messages:new Map(),draft:'',pending:null,attachment:null,gif:null,lastRead:0,hasMore:false,busy:false,readBusy:false});return conversations.get(value);};
 const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.content||'';
 const request=async(url,body)=>{
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{const r=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}: {})},...(body?{method:'POST',body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Please try again.');return d;}finally{clearTimeout(timer);}
 };
 const readOnly=()=>Boolean(teams.find(t=>String(t.user_id)===selected)?.read_only);
 const defaultConversation=()=>String(teams.find(t=>t.read_only&&Number(unread.people?.[t.user_id])>0)?.user_id||'');
 const title=()=>selected?(teams.find(t=>String(t.user_id)===selected)?.team_name||'Team messages'):'League chat';
 const persist=()=>{try{sessionStorage.setItem(key,JSON.stringify({mode,position,selected}));}catch(_){}};
 const constrain=()=>{
  if(maximized||!position||panel.hidden)return;const rect=panel.getBoundingClientRect(),nav=document.querySelector('.mobile-primary-nav'),bottom=nav&&getComputedStyle(nav).display!=='none'?nav.getBoundingClientRect().top:window.innerHeight;
  position.x=Math.max(8,Math.min(position.x,window.innerWidth-rect.width-8));position.y=Math.max(8,Math.min(position.y,bottom-rect.height-8));Object.assign(panel.style,{left:position.x+'px',top:position.y+'px',right:'auto'});
 };
 const fitMaximized=()=>{
  if(!maximized)return;
  let top=8,bottom=window.innerHeight-8;
  document.querySelectorAll('.site-header,.main-nav,.mobile-primary-nav').forEach(menu=>{
   if(getComputedStyle(menu).display==='none')return;const rect=menu.getBoundingClientRect();if(!rect.height)return;
   if(rect.top<window.innerHeight/2)top=Math.max(top,rect.bottom+8);else bottom=Math.min(bottom,rect.top-8);
  });
  panel.style.setProperty('--chat-max-top',top+'px');panel.style.setProperty('--chat-max-height',Math.max(80,bottom-top)+'px');
 };
 const render=()=>{panel.dataset.maximized=String(maximized&&mode==='expanded');maximize.textContent=maximized?'❐':'⛶';maximize.setAttribute('aria-label',maximized?'Restore chat size':'Maximize chat');maximize.title=maximized?'Restore size':'Maximize';fitMaximized();panel.hidden=mode==='closed';panel.dataset.minimized=String(mode==='minimized');restore.hidden=mode!=='minimized';restore.textContent=title();header?.setAttribute('aria-expanded',String(mode!=='closed'));constrain();persist();};
 const atBottom=()=>log.scrollHeight-log.scrollTop-log.clientHeight<40;
 const isReading=message=>mode==='expanded'&&!document.hidden&&atBottom()&&(message.recipient_id===null?selected==='':selected===String(message.sender_id));
 const counter=value=>{const el=document.getElementById('header-message-count');if(el){el.textContent=value>0?String(value):'';el.hidden=!(value>0);el.dataset.active=String(value>0);}};
 const read=async()=>{
  const value=selected,state=conversation(value);if(mode!=='expanded'||document.hidden||!atBottom()||state.readBusy)return;
  const ids=[...state.messages.keys()],id=ids.length?Math.max(...ids):0;if(id<=state.lastRead)return;state.readBusy=true;
  try{const d=await request('/api/messages/read',{user_id:value?Number(value):null,last_id:id});state.lastRead=Math.max(state.lastRead,id);updateUnread(d.unread);}catch(_){}finally{state.readBusy=false;}
 };
 const node=(tag,value,className)=>{const el=document.createElement(tag);if(value!==undefined)el.textContent=value;if(className)el.className=className;return el;};
 const draw=(oldPage=false)=>{
  const state=conversation(selected),bottom=atBottom(),height=log.scrollHeight;log.replaceChildren();
  const visibleMessages=[...state.messages.values()];let dateLabel=null;
  if(!visibleMessages.length)log.append(node('p','No messages yet. Start the conversation.'));
  visibleMessages.sort((a,b)=>a.id-b.id).forEach(message=>{
   const date=new Date(message.created_at).toLocaleDateString([],{timeZone:'America/Halifax',year:'numeric',month:'short',day:'numeric'});if(date!==dateLabel){log.append(node('div',date,'chat-date-divider'));dateLabel=date;}
   const row=node('article',undefined,'chat-message');row.dataset.own=String(Number(message.sender_id)===userId);
   const identity=node('div',undefined,'chat-message-identity'),logo=node('img');logo.src=message.team_logo||'/team-icons/league-logo/thumbnail?size=64';logo.alt='';logo.width=24;logo.height=24;const name=node('strong',message.team_name||'League member');name.title=name.textContent;identity.append(logo,name);
   const meta=node('div',undefined,'chat-message-meta');meta.append(identity);
   const time=node('time',new Date(message.created_at).toLocaleString([],{timeZone:'America/Halifax',hour:'numeric',minute:'2-digit',hour12:true}),'chat-message-time');
   const receipt=window.EcfhlMessageReceipt?.(message,userId);
   meta.append(time);row.append(meta,window.EcfhlChatContent.body(message));
   const actions=node('div',undefined,'chat-message-actions');actions.append(window.EcfhlChatContent.reaction(message,async active=>{try{const d=await request('/api/messages/'+message.id+'/reaction',{active});state.messages.set(Number(d.message.id),d.message);draw();}catch(e){status.textContent=e.message;}}));if(receipt)actions.append(receipt);row.append(actions);log.append(row);
  });
  older.hidden=!state.hasMore;if(oldPage)log.scrollTop+=log.scrollHeight-height;else if(bottom)log.scrollTop=log.scrollHeight;constrain();read();
 };
 const refresh=async(oldPage=false)=>{
  const value=selected,state=conversation(value),version=generation;if(mode!=='expanded'||document.hidden||state.busy||!directoryReady)return;state.busy=true;
  const ids=[...state.messages.keys()],params=new URLSearchParams();if(value)params.set('user_id',value);if(ids.length)params.set(oldPage?'before':'after',String(oldPage?Math.min(...ids):Math.max(...ids)));
  ids.slice(-250).forEach(id=>params.append('receipts[]',id));
  try{const d=await request('/api/messages/conversation?'+params);(d.receipts||[]).forEach(receipt=>{const message=state.messages.get(Number(receipt.id));if(message)Object.assign(message,receipt);});d.messages.forEach(m=>state.messages.set(Number(m.id),m));if(oldPage||!ids.length)state.hasMore=d.has_more;if(version===generation&&value===selected){status.textContent='';draw(oldPage);}}catch(e){if(version===generation)status.textContent=e.message;}finally{state.busy=false;}
 };
 const change=value=>{
  value=String(value||'');if(value&&!teams.some(t=>String(t.user_id)===value))value='';
  conversation(selected).draft=text.value;selected=value;generation++;select.value=value;text.value=conversation(value).draft;form.hidden=readOnly();attachmentPreview();status.textContent='';log.replaceChildren();draw();log.scrollTop=log.scrollHeight;render();refresh();
 };
 let requested=new URL(location.href).pathname==='/messages'?new URL(location.href).searchParams.get('user_id'):null;
 let pendingOpen=null;
 let requestedReply=location.pathname==='/messages'&&new URL(location.href).searchParams.get('reply')==='1';
 const open=value=>{
  if(!directoryReady){pendingOpen=String(value||'');mode='expanded';render();return;}
  mode='expanded';change(value);text.focus({preventScroll:true});
 };
const updateUnread=value=>{
  unread=value||unread;counter(unread.total);options();
 };
 const options=()=>{
  const count=n=>Number(n)>0?' ('+Number(n)+' unread)':'';
  select.replaceChildren(node('option','League chat'+count(unread.league)));select.firstElementChild.value='';
  teams.forEach(t=>{const option=node('option',t.team_name+count(unread.people?.[t.user_id]));option.value=String(t.user_id);select.append(option);});select.value=selected;
 };
 window.addEventListener('ecfhl-unread',event=>updateUnread(event.detail));
 const updateDirectory=data=>{
  if(!Array.isArray(data.teams))return;
  teams=[...data.teams,...(data.received_conversations||[])].filter(t=>Number(t.user_id)!==userId);
  if(data.unread)unread=data.unread;counter(unread.total);options();
  if(!directoryReady){
   directoryReady=true;const initial=pendingOpen!==null?pendingOpen:location.pathname==='/messages'?(requested||''):(defaultConversation()||saved.selected||'');if(requested!==null||location.pathname==='/messages')mode='expanded';change(initial);pendingOpen=null;if(requestedReply){text.focus({preventScroll:true});requestedReply=false;}
  }else if(selected&&!teams.some(t=>String(t.user_id)===selected))change('');else select.value=selected;
 };
 window.addEventListener('ecfhl-message-state',event=>updateDirectory(event.detail));
 document.addEventListener('click',event=>{
  const link=event.target.closest('a[href]');if(!link||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  const url=new URL(link.href,location.origin);if(url.origin!==location.origin||url.pathname!=='/messages')return;event.preventDefault();
  if(link.id==='team-icon-modal-message')document.getElementById('team-icon-modal-close')?.click();
  open(url.searchParams.has('user_id')?url.searchParams.get('user_id'):(link===header?defaultConversation():''));
 },true);
 const attachmentPreview=()=>{const preview=$('attachment'),state=conversation(selected),file=state.attachment,gif=state.gif;preview.hidden=!(file||gif);preview.querySelector('span').textContent=file?.name||gif?.label||'';const image=preview.querySelector('img');image.hidden=!(file||gif);image.src=file?.data||gif?.url||'';};
 $('attachment').querySelector('button').addEventListener('click',()=>{conversation(selected).attachment=null;conversation(selected).gif=null;attachmentPreview();});
 const insert=(before,after='',placeholder='text')=>{const start=text.selectionStart,end=text.selectionEnd,value=text.value.slice(start,end)||placeholder;text.setRangeText(before+value+after,start,end,'select');conversation(selected).draft=text.value;text.focus();};
 const gifCatalog=[['Thumbs up','l0Iy8h9qfiqRYgQp2','yes agree nice'],['Well done','l41YqNeoHIVsBHEnm','good job thumbs up'],['Applause','bOlMQ1kfrMuMz5Z7ta','clap bravo'],['Wow','CZwRAhJmiHEj3ERG7J','laugh funny hockey'],['Celebrate','UWtsCWTJqVXOqvdkQq','hockey goal win happy'],['Goal!','P7tMkxgqB1bRBfIFjJ','hockey celebrate happy'],['Victory','LrANfH6Gz78VDGebIj','hockey win celebrate'],['Party','mFTLWSmqBznjwyFXdK','dance happy hockey']];
 const closeGifs=()=>{$('gifs').hidden=true;form.querySelector('[data-chat-format="gif"]').setAttribute('aria-expanded','false');};
 const drawGifs=()=>{const grid=$('gif-grid'),query=$('gif-search').value.trim().toLowerCase();grid.replaceChildren();gifCatalog.filter(([label,id,tags])=>(label+' '+tags).toLowerCase().includes(query)).forEach(([label,id])=>{const url='https://media.giphy.com/media/'+id+'/200w.gif',button=node('button'),image=node('img');button.type='button';button.setAttribute('aria-label','Choose '+label+' GIF');image.src=url;image.alt=label;image.loading='lazy';image.referrerPolicy='no-referrer';button.append(image,node('span',label));image.addEventListener('error',()=>{button.disabled=true;button.title='GIF unavailable. Try another or upload your own.';});button.addEventListener('click',()=>{const state=conversation(selected);state.gif={url,label};state.attachment=null;closeGifs();attachmentPreview();text.focus();});grid.append(button);});if(!grid.childElementCount)grid.append(node('p','No matching reactions. Try “hockey” or upload a GIF.'));};
 $('gif-search').addEventListener('input',drawGifs);$('gif-close').addEventListener('click',closeGifs);$('gif-upload').addEventListener('click',()=>{$('gif-file').click();});
 $('gifs').addEventListener('keydown',event=>{if(event.key==='Escape'){event.stopPropagation();closeGifs();form.querySelector('[data-chat-format="gif"]').focus();}});
 form.querySelectorAll('[data-chat-format]').forEach(button=>button.addEventListener('click',()=>{
  const format=button.dataset.chatFormat;
  if(format==='bold')insert('**','**');else if(format==='italic')insert('*','*');else if(format==='strike')insert('~~','~~');else if(format==='list')insert(text.selectionStart?'\n- ':'- ','','item');
  else if(format==='gif'){const picker=$('gifs');picker.hidden=!picker.hidden;button.setAttribute('aria-expanded',String(!picker.hidden));if(!picker.hidden){drawGifs();$('gif-search').focus();}}
  else if(format==='link'){const value=prompt('Paste an HTTPS link');if(!value)return;try{const url=new URL(value);if(url.protocol!=='https:')throw Error();insert('[',']('+url.href+')','link');}catch{status.textContent='Use an HTTPS URL.';}}
  else if(format==='emoji')$('emoji').hidden=!$('emoji').hidden;else if(format==='image')$('file').click();
 }));
 form.querySelectorAll('[data-chat-emoji]').forEach(button=>button.addEventListener('click',()=>{insert(button.dataset.chatEmoji,'','');$('emoji').hidden=true;}));
 ['file','gif-file'].forEach(id=>$(id).addEventListener('change',()=>{const file=$(id).files[0];$(id).value='';if(!file)return;if(file.size>2097152||!['image/png','image/jpeg','image/gif','image/webp'].includes(file.type)){status.textContent='Choose a PNG, JPEG, GIF or WebP under 2 MB.';return;}const value=selected,reader=new FileReader();reader.onload=()=>{conversation(value).gif=null;conversation(value).attachment={name:file.name,data:reader.result};closeGifs();if(value===selected)attachmentPreview();};reader.readAsDataURL(file);}));
 select.addEventListener('change',()=>change(select.value));
 text.addEventListener('input',()=>{conversation(selected).draft=text.value;});
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(sending||readOnly()||(!text.value.trim()&&!conversation(selected).attachment&&!conversation(selected).gif)||!directoryReady)return;
  const value=selected,state=conversation(value),typedBody=text.value.trim(),body=typedBody+(state.gif?'\n![GIF]('+state.gif.url+')':'');if(!state.pending||state.pending.body!==body||state.pending.attachment!==state.attachment?.data)state.pending={body,client_id:crypto.randomUUID(),...(state.attachment?{attachment:state.attachment.data}:{})};
  const attempt=state.pending;sending=true;send.disabled=true;status.textContent='Sending…';
  try{const d=await request('/api/messages/send',{...attempt,user_id:value?Number(value):null});state.pending=null;state.attachment=null;state.gif=null;attachmentPreview();state.messages.set(Number(d.message.id),d.message);if(state.draft.trim()===typedBody)state.draft='';
   if(value===selected){if(text.value.trim()===typedBody)text.value='';draw();log.scrollTop=log.scrollHeight;await read();status.textContent='Sent.';}
  }catch(e){if(value===selected)status.textContent=e.message;}finally{sending=false;send.disabled=false;}
 });
 maximize.addEventListener('click',()=>{maximized=!maximized;mode='expanded';render();refresh();});
 const hide=()=>{mode='closed';render();header?.focus({preventScroll:true});};
 close.addEventListener('click',hide);$('minimize').addEventListener('click',()=>{mode='minimized';render();restore.focus({preventScroll:true});});
 restore.addEventListener('click',()=>{mode='expanded';render();refresh();read();close.focus({preventScroll:true});});
 older.addEventListener('click',()=>refresh(true));log.addEventListener('scroll',read);
 panel.addEventListener('keydown',event=>{if(event.key==='Escape')hide();});
 handle.addEventListener('pointerdown',event=>{if(maximized||event.button!==0||event.target.closest('button,a,select,input,label'))return;const rect=panel.getBoundingClientRect();drag={id:event.pointerId,x:event.clientX,y:event.clientY,left:rect.left,top:rect.top};handle.setPointerCapture(event.pointerId);panel.dataset.dragging='true';});
 handle.addEventListener('pointermove',event=>{if(!drag||event.pointerId!==drag.id)return;position={x:drag.left+event.clientX-drag.x,y:drag.top+event.clientY-drag.y};constrain();});
 const end=event=>{if(!drag||event.pointerId!==drag.id)return;if(handle.hasPointerCapture(event.pointerId))handle.releasePointerCapture(event.pointerId);drag=null;panel.dataset.dragging='false';persist();};
 ['pointerup','pointercancel','lostpointercapture'].forEach(type=>handle.addEventListener(type,end));
 handle.addEventListener('keydown',event=>{if(maximized||event.target!==handle||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const rect=panel.getBoundingClientRect(),step=event.shiftKey?30:10;position={x:rect.left+(event.key==='ArrowLeft'?-step:event.key==='ArrowRight'?step:0),y:rect.top+(event.key==='ArrowUp'?-step:event.key==='ArrowDown'?step:0)};constrain();persist();});
 panel.addEventListener('ecfhl-panel-resize',event=>{if(event.detail?.position)position=event.detail.position;constrain();persist();});
 window.addEventListener('resize',()=>{fitMaximized();constrain();persist();});window.addEventListener('scroll',fitMaximized,{passive:true});document.addEventListener('visibilitychange',()=>{if(!document.hidden){refresh();read();}});
 if('ResizeObserver' in window){const observer=new ResizeObserver(fitMaximized);document.querySelectorAll('.site-header,.main-nav,.mobile-primary-nav').forEach(menu=>observer.observe(menu));}
 window.EcfhlChatPanel={open,isReading};render();request('/api/messages/state').then(updateDirectory).catch(e=>status.textContent=e.message);setInterval(()=>refresh(),15000);
})();

