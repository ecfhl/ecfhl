(() => {
  const root=document.documentElement;
  const account=Boolean(root.dataset.themeAccount);
  let desired=root.dataset.theme, saved=desired, busy=false;
  const controls=()=>document.getElementById('profile-theme');
  const status=text=>{const node=document.getElementById('theme-save-status');if(node)node.textContent=text;};
  const save=async()=>{
    if(!account || busy || saved===desired)return;
    busy=true;
    const retry=document.getElementById('theme-save-retry');if(retry)retry.hidden=true;
    try{
      while(saved!==desired){
        const theme=desired;status('Saving theme…');
        const response=await fetch('/account/preferences',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({theme})});
        const data=await response.json();
        if(!response.ok || data.preferences?.theme!==theme)throw new Error('Theme could not be saved. Please retry.');
        saved=theme;
      }
      status('Theme saved to your account.');
    }catch(error){status(error.message);if(retry)retry.hidden=false;}
    finally{busy=false;}
  };
  const set=theme=>{
    if(!['dark','light'].includes(theme))return;
    desired=theme;root.dataset.theme=theme;
    if(!account)try{localStorage.setItem('ecfhl-theme',theme);}catch(_){}
    if(controls())controls().value=theme;
    window.syncThemeControl?.();save();
  };
  window.EcfhlTheme={set};
  document.addEventListener('DOMContentLoaded',()=>{
    if(controls()){controls().value=desired;controls().addEventListener('change',event=>set(event.target.value));}
    document.getElementById('theme-save-retry')?.addEventListener('click',save);
  });
})();
