(()=>{
 // A goalie can appear on both days; both checkboxes represent the same saved watch.
 const watches=[...document.querySelectorAll('.owner-goalie-choice input[name="goalies[]"]')];
 watches.forEach(input=>input.addEventListener('change',()=>{watches.filter(other=>other.value===input.value).forEach(other=>{other.checked=input.checked;});}));
 const form=document.getElementById('owner-preferences-form'),save=document.getElementById('owner-save-preferences'),saveState=document.getElementById('owner-save-state');
 if(form&&save){
  const settings=[...form.querySelectorAll('input[type="checkbox"]')].filter(input=>input.name&&input.name!=='goalies[]');
  const read=()=>{const preferences={};settings.forEach(input=>{preferences[input.name]=input.checked;});preferences.goalies=[...new Set(new FormData(form).getAll('goalies[]'))].sort();return preferences;};
  const signature=preferences=>JSON.stringify([...settings.map(input=>Boolean(preferences[input.name])),[...new Set(preferences.goalies||[])].sort()]);
  let saved=signature(read()),saving=false;
  const autosave=async()=>{
   if(saving||signature(read())===saved)return;
   saving=true;save.hidden=true;save.disabled=true;
   try{
    // Serialize writes and include changes made while the previous request was saving.
    while(signature(read())!==saved){
     const preferences=read();saveState.textContent='Saving preferences…';saveState.dataset.state='saving';
     const response=await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},body:JSON.stringify(preferences)});
     const data=await response.json();
     if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Could not save preferences. Please try again.');
     if(!data.preferences)throw new Error('Could not confirm the save. Please reload and try again.');
     if(signature(data.preferences)!==signature(preferences))throw new Error('Could not confirm all preferences. Please retry saving.');
     saved=signature(data.preferences);
     window.dispatchEvent(new CustomEvent('ecfhl-preferences-saved',{detail:data.preferences}));
    }
    saveState.textContent='All changes saved.';saveState.dataset.state='saved';
   }catch(error){saveState.textContent=error.message;saveState.dataset.state='error';save.hidden=false;}
   finally{saving=false;save.disabled=false;}
  };
  form.addEventListener('change',autosave);
  form.addEventListener('submit',event=>{event.preventDefault();autosave();});
 }
 const enable=document.getElementById('owner-enable-push'),disable=document.getElementById('owner-disable-push'),state=document.getElementById('owner-push-state');
 const supported='serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
 const enableLabel=enable.textContent||'Enable notifications',disableLabel=disable.textContent||'Disable on this device';
 const lockDevice=()=>{enable.disabled=true;disable.disabled=true;};
 const unlockDevice=()=>{enable.disabled=false;disable.disabled=false;};
 const finishDeviceAction=success=>{
  enable.textContent=enableLabel;disable.textContent=disableLabel;
  // Give the browser's push state time to settle before allowing the reverse action.
  if(success)setTimeout(unlockDevice,3000);else unlockDevice();
 };
 const csrf=()=>document.querySelector('meta[name="csrf-token"]').content;
 const post=async(url,body)=>{const r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),'Accept':'application/json'},body:JSON.stringify(body)});const data=await r.json();if(!r.ok)throw new Error(data.message||'Could not save this device.');return data;};
 const b64=s=>Uint8Array.from(atob((s+'='.repeat((4-s.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));
 let reg;
 const update=async()=>{const sub=await reg.pushManager.getSubscription();const status=await fetch('/push/device',{cache:'no-store'});const device=status.ok?await status.json():{};const active=Boolean(sub&&device.enabled);enable.hidden=active;disable.hidden=!active;state.textContent=active?'Notifications enabled on this device.':Notification.permission==='denied'?'Notifications are blocked. Allow them in your browser settings.':'Notifications are off on this device.';};
 lockDevice();
 if(!supported){state.textContent='This browser does not support push notifications. You can still save preferences.';return;}
 navigator.serviceWorker.register('/push-sw.js',{scope:'/'}).then(async()=>{reg=await navigator.serviceWorker.ready;await update();unlockDevice();}).catch(e=>{state.textContent=e.message;});
 enable.addEventListener('click',async()=>{if(enable.disabled)return;lockDevice();enable.textContent='Enabling…';let success=false;try{
  if(await Notification.requestPermission()!=='granted')throw new Error('Allow notifications in your browser to enable alerts.');
  if(!reg)reg=await navigator.serviceWorker.ready;
  const c=await fetch('/push/config',{cache:'no-store'});if(!c.ok)throw new Error('Could not load notifications.');const config=await c.json();
  let sub=await reg.pushManager.getSubscription();if(!sub)sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64(config.publicKey)});
  const saved=await post('/push/subscribe',{endpoint:sub.endpoint});
  // A MessageChannel acknowledges persistent token storage before enabling delivery.
  await new Promise((resolve,reject)=>{const channel=new MessageChannel();const timer=setTimeout(()=>reject(new Error('Please reload and try enabling notifications again.')),10000);channel.port1.onmessage=()=>{clearTimeout(timer);resolve();};reg.active.postMessage({type:'set-owner-feed',token:saved.feedToken,lastId:saved.latestId},[channel.port2]);});
  localStorage.setItem('ecfhl-owner-push-enabled','1');localStorage.removeItem('ecfhl-push-disabled:'+document.getElementById('communication-context')?.dataset.userId);await update();success=true;
 }catch(e){state.textContent=e.message;}finally{finishDeviceAction(success);}});
 disable.addEventListener('click',async()=>{if(disable.disabled)return;lockDevice();disable.textContent='Disabling…';let success=false;try{const sub=await reg.pushManager.getSubscription();if(sub){await post('/push/unsubscribe',{endpoint:sub.endpoint});await sub.unsubscribe();}reg.active.postMessage({type:'set-owner-feed',token:'',lastId:0});localStorage.removeItem('ecfhl-owner-push-enabled');localStorage.setItem('ecfhl-push-disabled:'+document.getElementById('communication-context')?.dataset.userId,'1');await update();success=true;}catch(e){state.textContent=e.message;}finally{finishDeviceAction(success);}});
})();
