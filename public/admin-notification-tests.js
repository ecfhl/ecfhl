(()=>{
 const pending=new WeakSet();
 const ready=()=>document.querySelectorAll('.admin-test-result').forEach(status=>{if(!status.textContent)status.textContent='Ready to send';});
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});else ready();
 const sendTest=async form=>{
  if(pending.has(form))return;
  const button=form.querySelector('[data-send-notification-test]'),status=form.querySelector('.admin-test-result');
  if(!button||!status)return;
  pending.add(form);button.disabled=true;button.textContent='Sending…';status.textContent='Sending test…';form.setAttribute('aria-busy','true');
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{
   const response=await fetch(form.action,{method:'POST',credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:new FormData(form)});
   const data=await response.json().catch(()=>({message:response.status===419?'Your session expired. Reload this page and try again.':'Could not send the test. Please reload and try again.'}));
   if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Could not send the test.');
   status.textContent=data.message||'Test sent to this device.';status.dataset.state='success';
  }catch(error){status.textContent=error.name==='AbortError'?'The test timed out. Please try again.':error.message;status.dataset.state='error';}
  finally{clearTimeout(timer);pending.delete(form);button.disabled=false;button.textContent='Send test';form.removeAttribute('aria-busy');}
 };
 document.addEventListener('click',event=>{const button=event.target.closest('[data-send-notification-test]');if(!button)return;event.preventDefault();const form=button.closest('.admin-notification-card');if(form)sendTest(form);});
 document.addEventListener('submit',event=>{if(!event.target.matches('.admin-notification-card'))return;event.preventDefault();sendTest(event.target);},true);
})();
