(()=>{
 document.querySelectorAll('.admin-notification-card').forEach(form=>form.addEventListener('submit',async event=>{
  event.preventDefault();const button=form.querySelector('button'),status=form.querySelector('.admin-test-result');if(button.disabled)return;
  button.disabled=true;status.textContent='Sending test…';
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{const response=await fetch(form.action,{method:'POST',credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'},body:new FormData(form)});const data=await response.json();
   if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Could not send the test.');
   status.textContent=data.message||'Test sent to this device.';
  }catch(error){status.textContent=error.name==='AbortError'?'The test timed out. Please try again.':error.message;}
  finally{clearTimeout(timer);button.disabled=false;}
 }));
})();
