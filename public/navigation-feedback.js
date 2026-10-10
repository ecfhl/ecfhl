(() => {
  let navigating = false;
  const loading = document.getElementById('navigation-loading');
  const message = document.getElementById('navigation-loading-message');
  const cancel = document.getElementById('navigation-cancel');
  const loadingImage = document.getElementById('navigation-loading-image');
  let teamLogos = {};
  try { teamLogos = JSON.parse(document.getElementById('navigation-team-logos')?.textContent || '{}'); } catch (_) {}
  const invitation = document.getElementById('guest-signup-dialog');
  const closeMenus = () => {
    document.body.classList.remove('nav-open');
    document.querySelectorAll('.nav-dropdown').forEach(menu => { menu.classList.remove('open'); menu.classList.add('menu-dismissed'); });
    document.querySelectorAll('.nav-toggle, .nav-dropdown-toggle').forEach(button => button.setAttribute('aria-expanded', 'false'));
  };
  const reset = () => {
    navigating = false;
    document.body.classList.remove('navigation-pending');
    document.querySelector('main')?.removeAttribute('aria-busy');
    if (loading) loading.hidden = true;
    if (cancel) cancel.hidden = true;
  };
  const showLoading = (logo, name) => {
    if (!loading) return;
    if (loadingImage) {
      loadingImage.hidden = true;
      loadingImage.onload = () => { loadingImage.hidden = false; };
      loadingImage.onerror = () => { loadingImage.hidden = true; };
      loadingImage.src = logo;
      if (loadingImage.complete && loadingImage.naturalWidth > 0) loadingImage.hidden = false;
      loadingImage.alt = `${name} logo`;
    }
    document.body.classList.add('navigation-pending');
    document.querySelector('main')?.setAttribute('aria-busy', 'true');
    message.textContent = 'Loading…';
    loading.hidden = false;
  };
  const destinationLogo = link => {
    const target = new URL(link.href, location.href);
    const teamMatch = target.pathname.match(/^\/teams\/(?:current\/)?([A-Za-z0-9-]+)\/?$/);
    if (teamMatch && !['league', 'current'].includes(teamMatch[1])) return teamLogos[teamMatch[1]] || {src: `/team-icons/${teamMatch[1]}/thumbnail?size=160`, name: link.dataset.loadingLabel || link.textContent.trim() || 'Team'};
    return {src: loading.dataset.leagueLogo, name: 'ECFHL'};
  };
  const isNavigation = (event, link) => {
    if (!link || event.button !== 0 || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return false;
    // Clicking an image-viewer trigger inside a link is a local modal action, not page navigation.
    // In particular, the league logo lives inside the header's Overview link.
    if (event.target.closest('.brand-logo, [data-team-icon-viewer]')) return false;
    if (link.hasAttribute('download')) return false;
    if (link.getAttribute('href') === '#') return false;
    if (!link.closest('.site-header, .mobile-primary-nav, #guest-signup-dialog') && !/^\/teams\/(?:current\/)?[A-Za-z0-9-]+\/?$/.test(new URL(link.href, location.href).pathname)) return false;
    if (link.target && link.target !== '_self') return false;
    const url = new URL(link.href, location.href);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== location.origin) return false;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;
    return true;
  };
  document.addEventListener('click', event => {
    if (navigating && isNavigation(event, event.target.closest('a[href]'))) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!isNavigation(event, link)) return;
    closeMenus();
    // Some menu entries handle the current page locally instead of navigating.
    if (event.defaultPrevented || !loading) return;
    if (invitation?.open) invitation.close();
    navigating = true;
    const logo = destinationLogo(link);
    showLoading(logo.src, logo.name);
  });
  cancel?.addEventListener('click', () => { window.stop(); reset(); });
  document.querySelectorAll('.nav-dropdown').forEach(menu => menu.addEventListener('pointerleave', () => menu.classList.remove('menu-dismissed')));
  window.addEventListener('pageshow', () => { closeMenus(); reset(); });
  window.addEventListener('pagehide', reset);
  window.addEventListener('beforeunload', () => {
    if (!navigating && loading) showLoading(loading.dataset.pageLogo, loading.dataset.pageName);
  });
  // The server-rendered loader covers initial entry and refresh while the page parses.
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', reset, {once: true});
  else reset();

  window.navigateToTeam=(url,label)=>{
    const link=document.createElement('a');link.href=url;link.dataset.loadingLabel=label;
    document.body.appendChild(link);link.click();link.remove();
  };
  // Use the actual menu links so My Team follows the user's saved team.
  const primaryNav = document.querySelector('.mobile-primary-nav');
  let swipe = null;
  const blockedSwipe = target => {
    if (document.body.classList.contains('navigation-pending') || document.body.classList.contains('nav-open') || document.querySelector('dialog[open]')) return true;
    if (target.closest('input,textarea,select,button,a,[contenteditable],dialog,[role="dialog"],.communication-panel,[data-panel-resize],.mobile-primary-nav')) return true;
    for (let element = target; element && element !== document.body; element = element.parentElement) {
      const style = getComputedStyle(element);
      if (element.scrollWidth > element.clientWidth + 2 && ['auto','scroll'].includes(style.overflowX)) return true;
    }
    return false;
  };
  document.addEventListener('touchstart', event => {
    swipe = null;
    if (!primaryNav || getComputedStyle(primaryNav).display === 'none' || event.touches.length !== 1 || blockedSwipe(event.target)) return;
    const touch = event.touches[0];
    // Leave screen-edge gestures to the browser and operating system.
    if (touch.clientX < 24 || touch.clientX > window.innerWidth - 24) return;
    swipe = {x:touch.clientX,y:touch.clientY,time:Date.now()};
  }, {passive:true});
  document.addEventListener('touchmove', event => {
    if (!swipe) return;
    if (event.touches.length !== 1 || Math.abs(event.touches[0].clientY - swipe.y) > 35) swipe = null;
  }, {passive:true});
  document.addEventListener('touchcancel', () => {swipe = null;}, {passive:true});
  document.addEventListener('touchend', event => {
    const start = swipe; swipe = null;
    if (!start || event.touches.length || event.changedTouches.length !== 1 || blockedSwipe(event.target)) return;
    const touch = event.changedTouches[0], dx = touch.clientX - start.x, dy = touch.clientY - start.y;
    if (Date.now() - start.time > 650 || Math.abs(dx) < 75 || Math.abs(dx) < Math.abs(dy) * 2.5 || Math.abs(dy) > 35) return;
    const links = [...primaryNav.querySelectorAll('a[href]')];
    const current = links.findIndex(link => new URL(link.href, location.href).pathname.replace(/\/$/,'') === location.pathname.replace(/\/$/,''));
    if (current < 0) return;
    const next = links[current + (dx < 0 ? 1 : -1)];
    if (next) next.click();
  }, {passive:true});
  if (!invitation) return;
  document.querySelectorAll('[data-dismiss-signup]').forEach(button => button.addEventListener('click', () => invitation.close()));
  // Browsing stays optional. Do not reopen the invitation on every page change.
  let seen = false;
  try { seen = sessionStorage.getItem('ecfhl-signup-invitation') === 'seen'; } catch (_) {}
  if (!seen) {
    invitation.showModal();
    try { sessionStorage.setItem('ecfhl-signup-invitation', 'seen'); } catch (_) {}
  }
})();
