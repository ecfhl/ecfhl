(() => {
  const directory = document.querySelector('[data-league-teams]');
  if (!directory) return;
  const search = directory.querySelector('[data-teams-search]');
  const sort = directory.querySelector('[data-teams-sort]');
  const body = directory.querySelector('[data-teams-rows]');
  const rows = [...body.querySelectorAll('[data-team-row]')];
  const empty = directory.querySelector('[data-teams-empty]');
  const reset = directory.querySelector('[data-teams-reset]');
  const count = directory.querySelector('[data-teams-count]');
  const collator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true });
  const normalize = value => value.normalize('NFKD').replace(/\p{M}/gu, '').toLocaleLowerCase();
  const rank = row => row.dataset.teamRank === '' ? Infinity : Number(row.dataset.teamRank);
  const points = row => row.dataset.teamPoints === '' ? null : Number(row.dataset.teamPoints);

  function arrange() {
    const query = normalize(search.value.trim());
    const ordered = [...rows].sort((a, b) => {
      const byName = collator.compare(a.dataset.teamName, b.dataset.teamName);
      if (sort.value === 'name') return byName;
      if (sort.value === 'points') {
        const aPoints = points(a), bPoints = points(b);
        if (aPoints === null && bPoints !== null) return 1;
        if (bPoints === null && aPoints !== null) return -1;
        return (bPoints ?? 0) - (aPoints ?? 0) || byName;
      }
      if (sort.value === 'mine') {
        const mineFirst = Number(b.dataset.teamMine) - Number(a.dataset.teamMine);
        if (mineFirst) return mineFirst;
      }
      return rank(a) - rank(b) || byName;
    });
    let visible = 0;
    ordered.forEach(row => {
      row.hidden = !normalize(row.dataset.teamName).includes(query);
      if (!row.hidden) visible++;
    });
    body.replaceChildren(...ordered);
    empty.hidden = visible > 0;
    reset.hidden = !query;
    count.textContent = query ? `${visible} of ${rows.length} teams` : `${rows.length} teams`;
  }
  search.addEventListener('input', arrange);
  sort.addEventListener('change', arrange);
  reset.addEventListener('click', () => { search.value = ''; arrange(); search.focus(); });
  arrange();
})();
