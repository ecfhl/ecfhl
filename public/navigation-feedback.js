(() => {
  let navigating = false;
  let slowTimer;
  const loading = document.getElementById('navigation-loading');
  const message = document.getElementById('navigation-loading-message');
  const cancel = document.getElementById('navigation-cancel');
  const invitation = document.getElementById('guest-signup-dialog');
  const closeMenus = () => {
    document.body.classList.remove('nav-open');
    document.querySelectorAll('.nav-dropdown').forEach(menu => { menu.classList.remove('open'); menu.classList.add('menu-dismissed'); });
    document.querySelectorAll('.nav-toggle, .nav-dropdown-toggle').forEach(button => button.setAttribute('aria-expanded', 'false'));
  };
  const reset = () => {
    navigating = false;
    clearTimeout(slowTimer);
    document.body.classList.remove('navigation-pending');
    document.querySelector('main')?.removeAttribute('aria-busy');
    if (loading) loading.hidden = true;
    if (cancel) cancel.hidden = true;
  };
  const isNavigation = (event, link) => {
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return false;
    if (!link.closest('.site-header, .mobile-primary-nav, #guest-signup-dialog') || link.hasAttribute('download')) return false;
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
    document.body.classList.add('navigation-pending');
    document.querySelector('main')?.setAttribute('aria-busy', 'true');
    message.textContent = 'Loading ' + (link.dataset.loadingLabel || link.textContent.trim().replace(/\s+/g, ' ')) + '…';
    loading.hidden = false;
    slowTimer = setTimeout(() => {
      message.textContent = 'Still loading. Please wait…';
      if (cancel) cancel.hidden = false;
    }, 10000);
  });
  cancel?.addEventListener('click', () => { window.stop(); reset(); });
  document.querySelectorAll('.nav-dropdown').forEach(menu => menu.addEventListener('pointerleave', () => menu.classList.remove('menu-dismissed')));
  window.addEventListener('pageshow', event => { if (event.persisted) { closeMenus(); reset(); } });

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
