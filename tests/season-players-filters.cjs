const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');

const make = () => {
  const events = {}, windowEvents = {}, requests = [], historyChanges = [], scrolls = [];
  const panel = () => {
    const elements = {
      'season-player-more': { dataset: {}, addEventListener() {}, removeEventListener() {} },
      'season-player-error': { textContent: '' },
      'season-player-count': {}, 'season-player-rows': {},
    };
    const advanced = { open: false }, table = { scrollLeft: 0 }, control = { textContent: 'Goaltenders', getAttribute() { return null; }, focus(options) { this.focusOptions = options; } };
    return { elements, advanced, table, control, style: {}, attributes: {},
      setAttribute(key, value) { this.attributes[key] = value; }, removeAttribute(key) { delete this.attributes[key]; },
      getBoundingClientRect: () => ({ height: 1800 }),
      querySelector: key => key === '.player-advanced' ? advanced : table,
      querySelectorAll: () => [control], replaceWith(next) { state.current = next; },
    };
  };
  const state = { current: panel() };
  const location = { href: 'https://ecfhl.win/players?positions=F%2CD', origin: 'https://ecfhl.win', pathname: '/players' };
  const context = {
    URL, URLSearchParams, AbortController, FormData: class { constructor(form) { return form.values; } },
    document: { querySelector: key => key === '.season-players' ? state.current : null,
      getElementById: key => state.current.elements[key], addEventListener: (key, handler) => events[key] = handler },
    window: { scrollX: 0, scrollY: 245, scrollTo(options) { scrolls.push(options); }, addEventListener: (key, handler) => windowEvents[key] = handler },
    location, history: { state: null,
      replaceState(data, _, url) { this.state = data; historyChanges.push(['replace', url]); },
      pushState(data, _, url) { this.state = data; location.href = url; historyChanges.push(['push', url]); } },
    DOMParser: class { parseFromString(html) { assert.equal(html, 'players page'); return { querySelector: () => panel() }; } },
    fetch(url, options) { return new Promise(resolve => requests.push({ url, options, resolve })); },
  };
  vm.runInNewContext(fs.readFileSync('public/season-players.js', 'utf8'), context);
  const click = (url, overrides = {}) => {
    const link = { href: url, target: '', textContent: 'Goaltenders', getAttribute() { return null; } };
    const event = { button: 0, defaultPrevented: false, target: { closest: () => link }, preventDefault() { this.defaultPrevented = true; }, ...overrides };
    events.click(event); return event;
  };
  const settle = async (index, ok = true) => {
    requests[index].resolve({ ok, text: async () => 'players page' });
    await new Promise(resolve => setImmediate(resolve));
  };
  return { state, events, windowEvents, requests, historyChanges, scrolls, click, settle, context };
};

(async () => {
  const ui = make();
  ui.state.current.advanced.open = true; ui.state.current.table.scrollLeft = 120;
  const event = ui.click('/players?positions=G');
  assert.equal(event.defaultPrevented, true);
  assert.equal(ui.requests[0].options.headers.Accept, 'text/html');
  assert.equal(ui.scrolls.length, 0, 'No scrolling while the filter request is pending.');
  await ui.settle(0);
  assert.equal(ui.state.current.advanced.open, true);
  assert.equal(ui.state.current.table.scrollLeft, 120);
  assert.equal(ui.state.current.style.minHeight, '1800px');
  assert.equal(ui.state.current.control.focusOptions.preventScroll, true);
  assert.equal(ui.scrolls.length, 1);
  assert.equal(ui.scrolls[0].top, 245);
  assert.equal(new URL(ui.context.location.href).searchParams.get('positions'), 'G');
  assert.equal(ui.state.current.attributes['aria-busy'], undefined);
  assert.equal(ui.click('/players?positions=F', { ctrlKey: true }).defaultPrevented, false);
  const race = make(); race.click('/players?positions=G'); race.click('/players?positions=D');
  assert.equal(race.requests[0].options.signal.aborted, true);
  await race.settle(1); await race.settle(0);
  assert.equal(new URL(race.context.location.href).searchParams.get('positions'), 'D');
  assert.equal(race.historyChanges.filter(change => change[0] === 'push').length, 1);
  const failure = make(), original = failure.state.current;
  failure.click('/players?positions=G'); await failure.settle(0, false);
  assert.equal(failure.state.current, original); assert.equal(failure.scrolls.length, 0);
  assert.match(original.elements['season-player-error'].textContent, /try again/);
  const form = { action: 'https://ecfhl.win/players', matches: () => true, values: [['positions', 'G'], ['team', 'beta'], ['q', 'Goalie']] };
  const submit = { target: form, preventDefault() { this.defaultPrevented = true; } };
  failure.events.submit(submit); assert.equal(submit.defaultPrevented, true);
  assert.equal(new URL(failure.requests[1].url).searchParams.get('positions'), 'G');
  await failure.settle(1);
  failure.windowEvents.popstate({ state: { playersScroll: { x: 0, y: 80 } } });
  await failure.settle(2); assert.equal(failure.scrolls.at(-1).top, 80);
  console.log('Player filter checks passed: no reload, stable viewport/horizontal scroll, exclusive URLs, preserved advanced controls, abort stale responses, modifier clicks, search/team forms, failure recovery and Back.');
})().catch(error => { console.error(error); process.exit(1); });
