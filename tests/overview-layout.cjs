const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
// Exercise repeated desktop/mobile changes: preserve node identity, reading order,
// and exactly one instance of each card when columns are created and removed.
class Element {
  constructor(name) { this.name = name; this.children = []; this.parent = null; }
  append(...nodes) {
    for (const node of nodes) {
      if (node.parent) node.parent.children.splice(node.parent.children.indexOf(node), 1);
      node.parent = this;
      this.children.push(node);
    }
  }
  replaceChildren(...nodes) {
    for (const node of this.children) node.parent = null;
    this.children = [];
    this.append(...nodes);
  }
}
const container = new Element('container');
const names = ['week', 'standings', 'watch', 'league'];
const cards = names.map(name => new Element(name));
container.append(...cards);
container.querySelector = selector => cards.find(card => selector.includes(`"${card.name}"`));
const classes = new Set();
container.classList = { add: name => classes.add(name), remove: name => classes.delete(name) };
const media = { matches: false, addEventListener(event, handler) { assert.equal(event, 'change'); this.change = handler; } };
const document = { querySelector: () => container, createElement: () => new Element('column') };
vm.runInNewContext(fs.readFileSync('public/overview-home.js', 'utf8'), { document, window: { matchMedia: () => media } });
assert.deepEqual(container.children, cards);
for (const desktop of [true, false, true, true, false, false]) {
  media.matches = desktop;
  media.change();
  if (desktop) {
    assert.equal(container.children.length, 2);
    assert.deepEqual(container.children[0].children, [cards[0], cards[2]]);
    assert.deepEqual(container.children[1].children, [cards[1], cards[3]]);
    assert.ok(classes.has('overview-home__cards--desktop'));
  } else {
    assert.deepEqual(container.children, cards);
    assert.equal(classes.size, 0);
  }
}
console.log('Home layout transitions passed: two independent desktop stacks, mobile document order, no duplicated or lost cards.');
