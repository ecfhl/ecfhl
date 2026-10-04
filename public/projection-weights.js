document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('projection-weights-form');
  if (!form) return;
  const inputs = [...form.querySelectorAll('input[type="range"]')];
  const save = document.getElementById('projection-save');
  const previewButton = document.getElementById('projection-preview-button');
  const panel = document.getElementById('projection-preview');
  const status = document.getElementById('projection-preview-status');
  const results = document.getElementById('projection-preview-results');
  const body = document.getElementById('projection-preview-players');
  const total = document.getElementById('projection-total');
  const help = document.getElementById('projection-total-help');
  const stored = JSON.parse(form.dataset.stored), defaults = JSON.parse(form.dataset.defaults);
  const key = input => input.id.slice(7);
  const signature = () => inputs.map(input => Math.round(Number(input.value) * 10)).join(',');
  let lastSignature = signature(), previewSignature = null, busy = false;
  const unitsLabel = value => value + ' unit' + (value === 1 ? '' : 's');

  const update = () => {
    const current = signature();
    if (current !== lastSignature) {
      previewSignature = null;
      lastSignature = current;
      panel.hidden = true;
    }
    const sum = inputs.reduce((value, input) => value + Math.round(Number(input.value) * 10), 0);
    const valid = inputs.every(input => input.checkValidity()) && sum === 100;
    const difference = 100 - sum;
    inputs.forEach(input => {
      const units = Number(input.value), percent = Math.round(units * 10);
      // Highlight the portion each slider could give up to remove the total excess.
      // At 110%, a 7-unit slider is gold through 6 units and red from 6 to 7.
      const excess = Math.max(0, -difference);
      input.style.setProperty('--slider-fill', percent + '%');
      input.style.setProperty('--slider-warning-start', Math.max(0, percent - excess) + '%');
      input.style.setProperty('--slider-warning-end', percent + '%');
      document.getElementById('value-' + key(input)).textContent = units + ' / 10 · ' + percent + '%';
      input.setAttribute('aria-valuetext', units + ' out of 10 units, ' + percent + ' percent');
      const target = Math.round((units + difference / 10) * 10) / 10;
      document.getElementById('adjust-' + key(input)).textContent = difference && target >= 0 && target <= 10
        ? (difference > 0 ? 'Increase' : 'Reduce') + ' to ' + target + ' / 10 (' + Math.round(target * 10) + '%) for a 100% total.' : '';
    });
    total.textContent = (sum / 10).toLocaleString(undefined, {maximumFractionDigits: 1}) + ' / 10 units · ' + sum + '%';
    total.classList.toggle('invalid', !valid);
    const changed = inputs.some(input => Math.round(Number(input.value) * 10) !== Number(stored[key(input)]));
    save.disabled = !valid || !changed || previewSignature !== current || busy;
    previewButton.disabled = !valid || busy;
    if (difference) help.textContent = (difference > 0 ? 'Add ' : 'Remove ') + unitsLabel(Math.abs(difference) / 10) + ' (' + Math.abs(difference) + '%) in total. Use one suggested slider adjustment, or spread the change across sliders.';
    else if (!valid) help.textContent = 'Use the slider steps and a total of 10 units (100%).';
    else if (previewSignature === current) help.textContent = changed ? 'Preview ready. Review the top 10 below, then save.' : 'You are previewing the saved weights.';
    else help.textContent = changed ? 'Total is 100%. Preview the top 10 before saving.' : 'Current weights are saved. Preview the top 10 or adjust the sliders.';
  };

  const cell = (row, value, className) => {
    const td = document.createElement('td');
    if (className) td.className = className;
    td.textContent = value;
    row.append(td);
    return td;
  };
  const render = data => {
    body.replaceChildren();
    data.players.forEach((player, index) => {
      const row = document.createElement('tr');
      cell(row, index + 1);
      const name = cell(row, player.name, 'projection-preview-name');
      const meta = document.createElement('small');
      meta.textContent = [player.team, player.position].filter(Boolean).join(' · ');
      name.append(meta);
      cell(row, Number(player.myproj).toFixed(2), 'projection-preview-score');
      cell(row, Number(player.season_fpts_per_game).toFixed(2), 'projection-preview-score');
      body.append(row);
    });
    status.textContent = data.players.length
      ? 'Top ' + data.players.length + ' of ' + data.count + ' players' + (data.stats_through ? ' · Stats through ' + data.stats_through : '') + '.'
      : 'No player stats have been collected yet.';
    results.hidden = data.players.length === 0;
  };

  previewButton.addEventListener('click', async () => {
    if (previewButton.disabled || busy) return;
    const requested = signature();
    const units = Object.fromEntries(inputs.map(input => [key(input), Number(input.value)]));
    busy = true;
    previewSignature = null;
    panel.hidden = false;
    results.hidden = true;
    status.textContent = 'Calculating the top 10…';
    previewButton.textContent = 'Previewing…';
    update();
    try {
      const send = token => fetch('/admin/projections/preview', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
        body: JSON.stringify({units}),
      });
      const csrfInput = form.querySelector('input[name="_token"]');
      let response = await send(csrfInput.value);
      if (response.status === 419) {
        const fresh = await fetch('/csrf-token', {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
        const token = await fresh.json();
        if (fresh.ok && token.token) { csrfInput.value = token.token; response = await send(token.token); }
      }
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Could not load the preview. Try again.');
      // A response for earlier slider positions must never enable saving newer weights.
      if (signature() !== requested) return;
      render(data);
      previewSignature = requested;
      panel.hidden = false;
    } catch (error) {
      if (signature() === requested) { panel.hidden = false; status.textContent = error.message || 'Could not load the preview. Try again.'; }
    } finally {
      busy = false;
      previewButton.textContent = 'Preview Top 10';
      update();
    }
  });

  inputs.forEach(input => input.addEventListener('input', update));
  document.getElementById('projection-reset').addEventListener('click', () => { inputs.forEach(input => input.value = defaults[key(input)] / 10); update(); });
  form.addEventListener('submit', event => {
    if (save.disabled || previewSignature !== signature()) { event.preventDefault(); return; }
    form.querySelectorAll('button[type="button"]').forEach(button => button.disabled = true);
  });
  window.addEventListener('pageshow', event => {
    if (event.persisted) { document.getElementById('projection-reset').disabled = false; busy = false; update(); }
  });
  update();
});
