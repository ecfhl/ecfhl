(()=>{
 window.EcfhlMessageReceipt=(message,userId)=>{
  const viewers=message.viewers||[];
  if(message.recipient_id!==null){
   if(Number(message.sender_id)!==Number(userId))return null;
   const status=document.createElement('small');status.className='chat-read-status';
   status.textContent='Unread';
   if(message.read){
    status.textContent='Read';
    if(message.read_at){const date=new Date(message.read_at);status.textContent='Read at '+date.toLocaleTimeString([],{timeZone:'America/Halifax',hour:'numeric',minute:'2-digit',hour12:true})+' on '+date.toLocaleDateString([],{timeZone:'America/Halifax',year:'numeric',month:'short',day:'numeric'});}
   }
   return status;
  }
  const icon=document.createElement('span');icon.className='chat-view-status';icon.tabIndex=0;icon.dataset.viewed=String(viewers.length>0);icon.textContent='👁';
  icon.title=viewers.length?'Seen by: '+viewers.map(viewer=>viewer.team_name).join(', '):'No one has viewed this message yet.';
  icon.setAttribute('aria-label',icon.title);return icon;
 };
})();
