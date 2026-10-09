(() => {
  const panels=[document.getElementById('notification-panel'),document.getElementById('live-score-updates')].filter(Boolean);
  const fit=()=>{
    const nav=document.querySelector('.mobile-primary-nav');
    const bottom=nav && getComputedStyle(nav).display!=='none' ? Math.max(8,window.innerHeight-nav.getBoundingClientRect().top+8) : 12;
    panels.forEach(panel=>panel.style.setProperty('--updates-bottom',bottom+'px'));
  };
  const close=panel=>panel.querySelector('[data-close-notifications],#live-score-updates-close')?.click();
  document.addEventListener('click',event=>{
    const toggle=event.target.closest('#header-notifications-toggle,#live-score-updates-toggle');
    if(toggle){
      const id=toggle.getAttribute('aria-controls');
      panels.filter(panel=>panel.id!==id&&!panel.hidden).forEach(close);
    }else if(!event.target.closest('#notification-panel,#live-score-updates'))panels.filter(panel=>!panel.hidden).forEach(close);
  });
  window.addEventListener('resize',fit);
  window.visualViewport?.addEventListener('resize',fit);
  fit();
})();
