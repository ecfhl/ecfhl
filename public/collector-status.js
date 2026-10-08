document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('.job-status');
  if (!root) return;
  const ticker = root.querySelector('.collector-ticker');
  const csrf = root.querySelector('input[name="_token"]').value;
  let running = false;
  const paint = (key, state) => {
    const card = root.querySelector(`[data-job-key="${key}"]`);
    if (!card) return;
    card.dataset.status = state.status;
    card.setAttribute('aria-busy', String(state.status === 'running'));
    card.querySelector('.collector-state').textContent = state.status;
    card.querySelector('.collector-message').textContent = state.message;
    const bar = card.querySelector('progress');
    bar.max = state.total || 1;
    if (state.status === 'running' && !state.total) bar.removeAttribute('value');
    else bar.value = state.status === 'success' ? bar.max : state.completed || 0;
    card.querySelector('.collector-units').textContent = state.total ? `${state.completed} / ${state.total} units checked` : (state.status === 'running' ? 'Working…' : state.status);
    card.querySelector('.collector-details pre').textContent = state.details || '';
    if (state.updated_at) card.querySelector('.collector-updated').textContent = state.updated_at;
  };
  const poll = async () => {
    const response = await fetch('/job-status/state', {headers:{Accept:'application/json'}, cache:'no-store'});
    if (!response.ok) throw new Error('Status unavailable — reload or sign in');
    const states = await response.json();
    Object.entries(states).forEach(([key, state]) => paint(key, state));
    const active = Object.entries(states).find(([, state]) => state.status === 'running');
    if (active) ticker.textContent = `${active[0]} · ${active[1].total ? `${active[1].completed}/${active[1].total} checked` : 'Working…'}`;
    return states;
  };
  const post = async path => {
    const response = await fetch(path, {method:'POST', headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':csrf}});
    const data = await response.json();
    if (!response.ok && !data.details) throw new Error(data.message || 'Request failed');
    return data;
  };
  root.querySelectorAll('.job-ajax-form').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    if (running) return;
    running = true;
    const buttons = root.querySelectorAll('.job-ajax-form button');
    buttons.forEach(button => button.disabled = true);
    const jobs = form.dataset.job === 'all' ? [...root.querySelectorAll('[data-job-key]')].map(card => card.dataset.jobKey) : [form.dataset.job];
    let failed = 0;
    try {
      for (const [index, key] of jobs.entries()) {
        ticker.textContent = `${index + 1}/${jobs.length} · ${key} · Starting…`;
        const data = await post(`/job-status/run/${key}`);
        if (!data.ok) failed++;
        await poll();
        ticker.textContent = `${index + 1}/${jobs.length} · ${key} · ${data.ok ? 'Completed' : 'Failed'}`;
      }
      ticker.textContent = `${jobs.length} checked · ${failed ? `${failed} failed — open Details` : 'Completed'}`;
    } catch (error) { ticker.textContent = error.message; }
    finally { running = false; buttons.forEach(button => button.disabled = false); }
  }));
  root.querySelector('.test-notification-form').addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button');
    button.disabled = true;
    try { const data = await post('/job-status/test-scoring-notification'); ticker.textContent = data.message || 'Notification sent'; }
    catch (error) { ticker.textContent = error.message; }
    finally { button.disabled = false; }
  });
  const watch = async () => {
    if (!document.hidden) { try { await poll(); } catch (error) { ticker.textContent = error.message; } }
    setTimeout(watch, running ? 1500 : 10000);
  };
  watch();
});
