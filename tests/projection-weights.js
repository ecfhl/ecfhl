const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
  constructor(props = {}) { Object.assign(this, {disabled: false, hidden: false, textContent: '', children: [], handlers: {}, dataset: {}, classList: {toggle() {}}, style: {values: {}, setProperty(key, value) { this.values[key] = value; }}}, props); }
  addEventListener(type, fn) { this.handlers[type] = fn; }
  setAttribute(key, value) { this[key] = value; }
  append(child) { this.children.push(child); }
  replaceChildren() { this.children = []; }
  checkValidity() { return Number(this.value) >= 0 && Number(this.value) <= 10 && Number.isInteger(Number(this.value) * 2); }
}
const stored = {fantrax: 60, season: 20, '7d': 20, '14d': 0, '21d': 0};
const inputs = Object.entries(stored).map(([key, value]) => new Element({id: 'weight-' + key, value: value / 10}));
const ids = ['projection-save', 'projection-preview-button', 'projection-preview', 'projection-preview-status', 'projection-preview-results', 'projection-preview-players', 'projection-total', 'projection-total-help', 'projection-reset'];
const elements = Object.fromEntries(ids.map(id => [id, new Element()]));
inputs.forEach(input => { elements['value-' + input.id.slice(7)] = new Element(); elements['adjust-' + input.id.slice(7)] = new Element(); });
const csrf = new Element({value: 'csrf'});
const form = new Element({dataset: {stored: JSON.stringify(stored), defaults: JSON.stringify({fantrax: 50, season: 0, '7d': 25, '14d': 15, '21d': 10})}});
form.querySelectorAll = selector => selector.includes('input') ? inputs : [elements['projection-reset'], elements['projection-preview-button']];
form.querySelector = () => csrf;
elements['projection-weights-form'] = form;
const callbacks = {}, requests = [];
let send;
const response = (data, status = 200) => ({ok: status === 200, status, json: async () => data});
const data = {players: [{name: 'Player One', team: 'MTL', position: 'F', myproj: 6.1234, season_fpts_per_game: 4}], count: 1000, stats_through: '2026-10-04'};
vm.runInNewContext(fs.readFileSync('public/projection-weights.js', 'utf8'), {
  document: {addEventListener(type, fn) { callbacks[type] = fn; }, getElementById(id) { return elements[id]; }, createElement() { return new Element(); }},
  window: {addEventListener() {}}, fetch: (url, options) => { requests.push({url, options}); return send(url, options); },
});
callbacks.DOMContentLoaded();
const save = elements['projection-save'], preview = elements['projection-preview-button'];
const change = (key, value) => { const input = inputs.find(input => input.id === 'weight-' + key); input.value = value; input.handlers.input(); };
const click = () => preview.handlers.click();
(async () => {
  assert.equal(save.disabled, true);
  assert.equal(preview.disabled, false, 'Saved weights can be previewed.');
  change('fantrax', 4);
  assert.match(elements['projection-total-help'].textContent, /Add 2 units \(20%\)/);
  assert.match(elements['adjust-season'].textContent, /Increase to 4 \/ 10 \(40%\)/);
  assert.equal(preview.disabled, true);
  change('fantrax', 7);
  assert.equal(inputs[0].style.values['--slider-warning-start'], '60%', 'At 110%, the last unit of the 7-unit slider is red.');
  assert.equal(inputs[0].style.values['--slider-warning-end'], '70%');
  for (const input of inputs.slice(1, 3)) {
    assert.equal(input.style.values['--slider-warning-start'], '10%', 'The last unit of each 2-unit slider is red.');
    assert.equal(input.style.values['--slider-warning-end'], '20%');
  }
  assert.equal(inputs[3].style.values['--slider-warning-start'], '0%', 'Empty sliders have no red segment.');
  change('fantrax', 8);
  assert.match(elements['projection-total-help'].textContent, /Remove 2 units \(20%\)/);
  assert.match(elements['adjust-season'].textContent, /Reduce to 0 \/ 10 \(0%\)/);
  change('fantrax', 7); change('season', 1);
  assert.equal(inputs[0].style.values['--slider-warning-start'], inputs[0].style.values['--slider-warning-end'], 'Red disappears at a 100% total.');
  assert.equal(save.disabled, true, 'Valid changes still require a preview.');
  send = async () => response(data);
  await click();
  assert.equal(save.disabled, false);
  assert.equal(elements['projection-preview-players'].children[0].children[2].textContent, '6.12');
  assert.equal(elements['projection-preview-players'].children[0].children[3].textContent, '4.00');
  change('fantrax', 6); change('season', 2);
  assert.equal(save.disabled, true);
  assert.equal(elements['projection-preview'].hidden, true, 'Changing weights hides stale preview results.');
  let release;
  send = () => new Promise(resolve => { release = resolve; });
  const pending = click();
  change('fantrax', 7); change('season', 1);
  release(response(data)); await pending;
  assert.equal(save.disabled, true, 'An earlier response cannot unlock saving changed weights.');
  assert.equal(elements['projection-preview'].hidden, true);
  send = async () => response({message: 'Preview failed'}, 500);
  await click();
  assert.equal(save.disabled, true);
  assert.equal(elements['projection-preview-status'].textContent, 'Preview failed');
  let attempts = 0;
  send = async url => url === '/csrf-token' ? response({token: 'fresh'}) : (++attempts === 1 ? response({}, 419) : response(data));
  await click();
  assert.equal(csrf.value, 'fresh');
  assert.equal(save.disabled, false);
  assert.equal(JSON.parse(requests.at(-1).options.body).units.fantrax, 7);
  elements['projection-reset'].handlers.click();
  assert.equal(inputs[2].value, 2.5);
  assert.equal(save.disabled, true, 'Resetting to defaults requires a fresh preview.');
  let cancelled = false; form.handlers.submit({preventDefault() { cancelled = true; }});
  assert.equal(cancelled, true);
  console.log('Projection UI checks passed: exact adjustment guidance, preview gating, comparison values, stale-response rejection, failures, CSRF retry and defaults.');
})().catch(error => { console.error(error); process.exitCode = 1; });
