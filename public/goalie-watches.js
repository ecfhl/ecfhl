(()=>{
 const buttons=()=>[...document.querySelectorAll('[data-goalie-watch]')];
 const message=document.createElement('div');message.className='goalie-watch-message';message.setAttribute('role','status');message.hidden=true;document.body.append(message);let timer;
 const announce=(text,settings=false)=>{message.replaceChildren(document.createTextNode(text));if(settings){const link=document.createElement('a');link.href='/notifications';link.textContent='Device settings';message.append(link);}message.hidden=false;clearTimeout(timer);timer=setTimeout(()=>{message.hidden=true;},8000);};
 document.addEventListener('click',async event=>{
  const button=event.target.closest('[data-goalie-watch]');if(!button)return;
  event.preventDefault();
  if(button.disabled)return;
  const key=button.dataset.goalieWatch,enabled=button.getAttribute('aria-pressed')!=='true';
  const matches=buttons().filter(other=>other.dataset.goalieWatch===key);matches.forEach(other=>{other.disabled=true;});
  try{
   const response=await fetch('/notifications/goalie',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({key,enabled,player_id:button.dataset.goaliePlayerId||undefined})});
   if(response.status===401){location.assign('/login');return;}
   const data=await response.json();if(!response.ok)throw new Error(data.message||'Could not save this goalie watch.');
   matches.forEach(other=>{other.setAttribute('aria-pressed',String(data.enabled));other.setAttribute('aria-label',(data.enabled?'Stop watching ':'Watch ')+other.dataset.goalieName+' status changes');other.title=data.enabled?'Notifications on · click to turn off':'Notify me when this goalie’s status changes';});
   announce(data.enabled?'Watching '+button.dataset.goalieName+'. Enable alerts on your device to receive notifications.':'Stopped watching '+button.dataset.goalieName+'.',data.enabled);
  }catch(error){announce(error.message);}finally{matches.forEach(other=>{other.disabled=false;});}
 });
})();
