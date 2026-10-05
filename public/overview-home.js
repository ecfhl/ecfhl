/* One set of cards. Mobile always restores the server's normal document flow. */
(() => {
  const container = document.querySelector('[data-overview-cards]');
  if (!container) return;
  const cards = ['week', 'standings', 'watch', 'league'].map(name =>
    container.querySelector(`[data-overview-card="${name}"]`));
  if (cards.some(card => !card)) return;
  const desktop = window.matchMedia('(min-width: 901px)');
  const columns = [document.createElement('div'), document.createElement('div')];
  columns.forEach(column => { column.className = 'overview-home__column'; });
  function arrange() {
    if (desktop.matches) {
      columns[0].append(cards[0], cards[2]);
      columns[1].append(cards[1], cards[3]);
      container.replaceChildren(...columns);
      container.classList.add('overview-home__cards--desktop');
    } else {
      container.replaceChildren(...cards);
      container.classList.remove('overview-home__cards--desktop');
    }
  }
  arrange();
  desktop.addEventListener('change', arrange);
})();
