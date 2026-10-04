(() => {
  const pending = new Map();
  const label = button => button.dataset.loadingText || (
    /sign in/i.test(button.textContent || button.value) ? 'Signing in…' :
    /create account/i.test(button.textContent || button.value) ? 'Creating account…' :
    /upload/i.test(button.textContent || button.value) ? 'Uploading…' :
    /search/i.test(button.textContent || button.value) ? 'Searching…' :
    /save/i.test(button.textContent || button.value) ? 'Saving…' : 'Please wait…'
  );

  // Capture repeated submits, including Enter and requestSubmit(), before handlers run.
  document.addEventListener('submit', event => {
    if (!pending.has(event.target)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
  }, true);

  // Run after local handlers: cancelled confirmations and AJAX forms own their state.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method === 'dialog') return;
    const buttons = [...form.elements].filter(el =>
      (el instanceof HTMLButtonElement || el instanceof HTMLInputElement) &&
      ['submit', 'image'].includes(el.type)
    );
    const state = { busy: form.getAttribute('aria-busy'), buttons: [], hidden: null };
    pending.set(form, state);
    form.setAttribute('aria-busy', 'true');

    // Disabled submitters are omitted from form data; keep the chosen action's value.
    const submitter = event.submitter;
    if (submitter?.name && !submitter.disabled && submitter.type !== 'image') {
      state.hidden = document.createElement('input');
      state.hidden.type = 'hidden';
      state.hidden.name = submitter.name;
      state.hidden.value = submitter.value;
      form.append(state.hidden);
    }
    buttons.forEach(button => {
      if (button.disabled) return;
      const input = button instanceof HTMLInputElement;
      state.buttons.push({ button, input, content: input ? button.value : button.innerHTML });
      const loading = label(button);
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.classList.add('submit-pending');
      if (input) button.value = loading;
      else button.textContent = loading;
    });
  });

  // Mobile Back can restore a page from memory, including its disabled buttons.
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    pending.forEach((state, form) => {
      if (state.busy === null) form.removeAttribute('aria-busy');
      else form.setAttribute('aria-busy', state.busy);
      state.hidden?.remove();
      state.buttons.forEach(({ button, input, content }) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.classList.remove('submit-pending');
        if (input) button.value = content;
        else button.innerHTML = content;
      });
    });
    pending.clear();
  });
})();
