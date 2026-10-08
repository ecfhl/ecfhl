(()=>{
 const tabs=[...document.querySelectorAll('[data-settings-tab]')];
 const show=(name,focus=false)=>{
  tabs.forEach(tab=>{const active=tab.dataset.settingsTab===name;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;document.getElementById('settings-'+tab.dataset.settingsTab).hidden=!active;if(active&&focus)tab.focus();});
  const device=document.querySelector('[data-notification-device]');if(device)device.hidden=name!=='notifications';
 };
 tabs.forEach(tab=>{tab.addEventListener('click',()=>{show(tab.dataset.settingsTab);history.replaceState(null,'','#'+tab.dataset.settingsTab);});tab.addEventListener('keydown',event=>{if(['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){event.preventDefault();const next=event.key==='Home'?'notifications':event.key==='End'?'alerts':tab.dataset.settingsTab==='notifications'?'alerts':'notifications';show(next,true);history.replaceState(null,'','#'+next);}});});
 show(location.hash==='#alerts'?'alerts':'notifications');
 const host=document.getElementById('scoring-settings-controls'),filters=document.querySelector('.live-score-updates-filters');
 if(host&&filters){host.append(filters);filters.hidden=false;}
})();