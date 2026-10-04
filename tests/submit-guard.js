// Native-form interaction checks; AJAX preferences have their own async regression test.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
  constructor(props = {}) {
    Object.assign(this, {dataset: {}, attributes: {}, disabled: false, innerHTML: '', textContent: '', value: '', name: ''}, props);
    this.classList = {add() {}, remove() {}};
  }
  getAttribute(name) { return this.attributes[name] ?? null; }
  setAttribute(name, value) { this.attributes[name] = value; }
  removeAttribute(name) { delete this.attributes[name]; }
  remove() { this.form.elements.splice(this.form.elements.indexOf(this), 1); }
}
class Button extends Element { constructor(props) { super({type: 'submit', ...props}); } }
class Input extends Element {}
class Form extends Element {
  constructor(elements, props) { super({method: 'post', elements, ...props}); }
  append(el) { this.elements.push(el); el.form = this; }
}
const capture = [], bubble = [], pageShow = [];
const document = {
  addEventListener(name, fn, capturing) { (capturing ? capture : bubble).push(fn); },
  createElement() { return new Input(); },
};
vm.runInNewContext(fs.readFileSync('public/submit-guard.js', 'utf8'), {
  document, window: {addEventListener(name, fn) { pageShow.push(fn); }},
  HTMLFormElement: Form, HTMLButtonElement: Button, HTMLInputElement: Input, Map,
});
function submit(form, submitter, localHandler) {
  const event = {target: form, submitter, defaultPrevented: false, stopped: false,
    preventDefault() { this.defaultPrevented = true; },
    stopImmediatePropagation() { this.stopped = true; }};
  capture.forEach(fn => fn(event));
  if (!event.stopped) {
    localHandler?.(event);
    bubble.forEach(fn => fn(event));
  }
  return event;
}
function back() { pageShow.forEach(fn => fn({persisted: true})); }
const button = text => new Button({textContent: text, innerHTML: text});

for (const [text, expected] of [['Sign in', 'Signing in…'], ['Create account & claim team', 'Creating account…'], ['Save password', 'Saving…'], ['Upload', 'Uploading…'], ['Search', 'Searching…'], ['Claim team', 'Please wait…']]) {
  const save = button(text), other = button('Other action'), alreadyDisabled = new Button({disabled: true});
  const email = new Input({type: 'email', name: 'email', value: 'test@example.org'});
  const form = new Form([email, save, other, alreadyDisabled]);
  assert.equal(submit(form, save).defaultPrevented, false);
  assert.equal(save.disabled, true, `${text} must disable immediately`);
  assert.equal(other.disabled, true, 'Alternate submit buttons cannot submit the same form');
  assert.equal(save.textContent, expected);
  assert.equal(form.getAttribute('aria-busy'), 'true');
  assert.equal(email.disabled, false, 'Form values must remain in the request');
  pageShow.forEach(fn => fn({persisted: false}));
  assert.equal(save.disabled, true, 'Finishing initial page load must not unlock a submission');
  let handlerCalls = 0;
  for (let i = 0; i < 5; i++) {
    assert.equal(submit(form, i % 2 ? other : undefined, () => handlerCalls++).defaultPrevented, true);
  }
  assert.equal(handlerCalls, 0, 'Enter/repeated submit must not reach request handlers');
  back();
  assert.equal(save.disabled, false, 'Mobile Back must restore controls');
  assert.equal(other.disabled, false);
  assert.equal(alreadyDisabled.disabled, true, 'Originally disabled controls must stay disabled');
  assert.equal(save.innerHTML, text);
  assert.equal(form.getAttribute('aria-busy'), null);
  assert.equal(submit(form, save).defaultPrevented, false, 'Back must permit another submission');
  back();
}
const save = button('Save'), form = new Form([save]);
submit(form, save, event => event.preventDefault());
assert.equal(save.disabled, false, 'Cancelled confirmation must leave the form usable');
assert.equal(submit(form, save).defaultPrevented, false, 'Cancelling must not claim the form');
back();

// AJAX handlers cancel browser navigation and manage loading/error/dirty states themselves.
submit(form, save, event => { event.preventDefault(); save.disabled = true; save.textContent = 'Custom progress'; });
assert.equal(save.textContent, 'Custom progress');
back();
assert.equal(save.disabled, true, 'Native guard must not unlock a pending AJAX request');
save.disabled = false;
const named = new Input({type: 'submit', name: 'action', value: 'publish'}), namedForm = new Form([named]);
submit(namedForm, named);
assert.equal(named.disabled, true);
assert.equal(namedForm.elements.find(el => el.type === 'hidden').value, 'publish', 'Submitter action must be retained');
back();
assert.equal(named.value, 'publish');
assert.equal(namedForm.elements.length, 1, 'Back must remove temporary action input');
const first = new Form([button('Save')]), second = new Form([button('Save')]);
submit(first, first.elements[0]);
assert.equal(submit(second, second.elements[0]).defaultPrevented, false, 'Independent forms must remain usable');
back();
console.log('Submit UI checks passed: signup/login/save/upload/search/claim, alternate buttons, repeated Enter/submits, preserved form data, cancelled confirmations, AJAX ownership, independent forms and mobile Back recovery.');
