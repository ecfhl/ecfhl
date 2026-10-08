(()=>{
 const tabs=[...document.querySelectorAll('[data-settings-tab]')];
 const show=(name,focus=false)=>{
  tabs.forEach(tab=>{const active=tab.dataset.settingsTab===name;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;document.getElementById('settings-'+tab.dataset.settingsTab).hidden=!active;if(active&&focus)tab.focus();});
  const device=document.querySelector('[data-notification-device]');if(device)device.hidden=name!=='notifications';
 };
 tabs.forEach(tab=>{tab.addEventListener('click',()=>{show(tab.dataset.settingsTab);history.replaceState(null,'','#'+tab.dataset.settingsTab);});tab.addEventListener('keydown',event=>{if(['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){event.preventDefault();const next=event.key==='Home'?'notifications':event.key==='End'?'alerts':tab.dataset.settingsTab==='notifications'?'alerts':'notifications';show(next,true);history.replaceState(null,'','#'+next);}});});
 show(location.hash==='#alerts'?'alerts':'notifications');
 const host=document.getElementById('scoring-settings-controls'),filters=document.querySelector('.live-score-updates-filters');
 if(host&&filters){
  host.append(filters);filters.hidden=false;
  const toggle=document.getElementById('scoring-panel-enabled'),scoring=window.EcfhlScoreUpdates?.start();
  if(toggle&&scoring){const sync=()=>{toggle.checked=scoring.enabled();host.hidden=!toggle.checked;};sync();toggle.addEventListener('change',()=>{scoring.setEnabled(toggle.checked);sync();});}
 }
 const testStatus=document.getElementById('message-test-status');
 document.querySelectorAll('[data-message-test]').forEach(button=>button.addEventListener('click',async()=>{
  const type=button.dataset.messageTest,channel=button.dataset.testChannel;
  if(channel==='popup'){
   const enabled=document.querySelector('[name="'+(type==='league'?'league_message_popups':'private_message_popups')+'"][type="checkbox"]')?.checked;
   if(!enabled){testStatus.textContent='Turn on '+(type==='league'?'League chat':'Private message')+' popups first.';return;}
   window.dispatchEvent(new CustomEvent('ecfhl-test-message',{detail:{sender_id:-1,recipient_id:type==='league'?null:Number(document.getElementById('communication-context').dataset.userId),team_name:'Test message',body:'This is a '+(type==='league'?'League chat':'private message')+' notification test.',created_at:new Date().toISOString()}}));testStatus.textContent='Test popup shown. No message was sent.';return;
  }
  button.disabled=true;testStatus.textContent='Sending test notification…';
  try{const response=await fetch('/notifications/test-message',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({type})});const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Could not send test notification.');testStatus.textContent=data.message;}
  catch(error){testStatus.textContent=error.message;}finally{button.disabled=false;}
 }));
})();