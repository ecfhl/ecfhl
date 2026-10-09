(() => {
  const panels=[document.getElementById('notification-panel'),document.getElementById('live-score-updates')].filter(Boolean);
  const fit=()=>{
    const header=document.querySelector('.site-header');
    const top=Math.max(8,Math.round(header?.getBoundingClientRect().bottom ?? 72)+8);
    const nav=document.querySelector('.mobile-primary-nav');
    const bottom=nav && getComputedStyle(nav).display!=='none' ? Math.max(8,window.innerHeight-nav.getBoundingClientRect().top+8) : 12;
    panels.forEach(panel=>{
      panel.style.setProperty('--updates-top',top+'px');
      panel.style.setProperty('--updates-available-height',Math.max(48,window.innerHeight-top-bottom)+'px');
    });
  };
  const close=panel=>panel.querySelector('[data-close-notifications],#live-score-updates-close')?.click();
  document.addEventListener('click',event=>{
    const toggle=event.target.closest('#header-notifications-toggle,#live-score-updates-toggle');
    if(toggle){
      fit();
      const id=toggle.getAttribute('aria-controls');
      panels.filter(panel=>panel.id!==id&&!panel.hidden).forEach(close);
    }else if(!event.target.closest('#notification-panel,#live-score-updates'))panels.filter(panel=>!panel.hidden).forEach(close);
  });
  window.addEventListener('resize',fit);
  window.visualViewport?.addEventListener('resize',fit);
  window.addEventListener('scroll',fit,{passive:true});
  if(headerObserverSupported())new ResizeObserver(fit).observe(document.querySelector('.site-header'));
  function headerObserverSupported(){return typeof ResizeObserver!=='undefined' && document.querySelector('.site-header');}
  fit();
})();
