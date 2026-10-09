(() => {
  let refreshing=false;
  let sortColumn=0, sortDirection='asc';
  let columnWidths=null, resizing=null;
  const applyWidths=table=>{
    if(!columnWidths)return;
    table.dataset.resized='true';
    let total=0;
    [...table.tHead.rows[0].cells].forEach((header,index)=>{
      const width=columnWidths[index];
      if(header.getBoundingClientRect().width>0)total+=width;
      [header,...[...table.tBodies[0].rows].map(row=>row.cells[index])].forEach(cell=>{
        cell.style.setProperty('width',width+'px','important');
        cell.style.setProperty('max-width',width+'px','important');
      });
      header.querySelector('.standings-resize').setAttribute('aria-valuenow',String(width));
    });
    table.style.setProperty('width',total+'px','important');
    table.style.setProperty('min-width',total+'px','important');
  };
  const captureWidths=table=>{
    if(!columnWidths)columnWidths=[...table.tHead.rows[0].cells].map(cell=>Math.max(48,Math.round(cell.getBoundingClientRect().width)||80));
  };
  document.addEventListener('pointerdown',event=>{
    const handle=event.target.closest('.standings-resize');
    if(!handle || event.button!==0)return;
    event.preventDefault();
    const table=handle.closest('table');
    captureWidths(table);
    const column=Number(handle.dataset.column);
    resizing={handle,table,column,id:event.pointerId,x:event.clientX,width:columnWidths[column]};
    handle.setPointerCapture(event.pointerId);
    table.dataset.resizing='true';
    applyWidths(table);
  });
  document.addEventListener('pointermove',event=>{
    if(!resizing || event.pointerId!==resizing.id)return;
    columnWidths[resizing.column]=Math.max(48,Math.min(600,Math.round(resizing.width+event.clientX-resizing.x)));
    applyWidths(resizing.table);
  });
  const endResize=event=>{
    if(!resizing || event.pointerId!==resizing.id)return;
    const {handle,table,id}=resizing;
    resizing=null;
    table.dataset.resizing='false';
    if(handle.hasPointerCapture(id))handle.releasePointerCapture(id);
  };
  ['pointerup','pointercancel','lostpointercapture'].forEach(type=>document.addEventListener(type,endResize));
  document.addEventListener('keydown',event=>{
    const handle=event.target.closest('.standings-resize');
    if(!handle || !['ArrowLeft','ArrowRight'].includes(event.key))return;
    event.preventDefault();
    const table=handle.closest('table');
    captureWidths(table);
    const column=Number(handle.dataset.column);
    columnWidths[column]=Math.max(48,Math.min(600,columnWidths[column]+(event.key==='ArrowRight'?1:-1)*(event.shiftKey?30:10)));
    applyWidths(table);
  });
  if(typeof window!=='undefined')window.addEventListener?.('resize',()=>document.querySelectorAll('.standings-sortable').forEach(applyWidths));
  const sortTable=table=>{
    const body=table.tBodies[0];
    if(!body)return;
    const value=row=>{
      const cell=row.cells[sortColumn];
      const raw=(cell.dataset.sortValue ?? cell.textContent).trim();
      if(!raw || raw==='—')return null;
      return sortColumn===1 ? raw : Number(raw.replace(/[, %]/g,''));
    };
    const rows=[...body.rows].map((row,index)=>({row,index,value:value(row)}));
    rows.sort((a,b)=>{
      if(a.value===null || b.value===null)return a.value===b.value?a.index-b.index:a.value===null?1:-1;
      const comparison=sortColumn===1?a.value.localeCompare(b.value,undefined,{numeric:true,sensitivity:'base'}):a.value-b.value;
      return comparison*(sortDirection==='asc'?1:-1)||a.index-b.index;
    });
    rows.forEach(({row})=>body.append(row));
    table.dataset.sortedColumn=String(sortColumn);
    table.querySelectorAll('.standings-sort').forEach(button=>{
      const active=Number(button.dataset.column)===sortColumn;
      button.closest('th').setAttribute('aria-sort',active?(sortDirection==='asc'?'ascending':'descending'):'none');
      button.querySelector('.standings-sort-arrow').textContent=active?(sortDirection==='asc'?' ↑':' ↓'):' ↕';
    });
  };
  document.addEventListener('click',event=>{
    const button=event.target.closest('.standings-sort');
    if(!button)return;
    const column=Number(button.dataset.column);
    sortDirection=column===sortColumn?(sortDirection==='asc'?'desc':'asc'):button.dataset.defaultDirection;
    sortColumn=column;
    sortTable(button.closest('table'));
  });
  const refresh=async()=>{
    const current=document.querySelector('.standings-page');
    if(!current||document.hidden||refreshing||resizing||document.querySelector('#team-icon-modal.open, dialog[open]'))return;
    refreshing=true;
    const controller=new AbortController();
    const timeout=setTimeout(()=>controller.abort(),20000);
    try{
      const response=await fetch(window.location.href,{
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},cache:'no-store',signal:controller.signal
      });
      if(!response.ok)throw new Error('Standings refresh failed');
      const doc=new DOMParser().parseFromString(await response.text(),'text/html');
      const fresh=doc.querySelector('.standings-page');
      if(!fresh)throw new Error('Missing standings');
      if(document.hidden||resizing||document.querySelector('#team-icon-modal.open, dialog[open]'))return;
      // Capture state after fetching so a click during a slow request is preserved.
      const states=new Map([...current.querySelectorAll('.standings-period')].map(el=>[el.dataset.periodNumber,el.open]));
      fresh.querySelectorAll('.standings-period').forEach(el=>{if(states.has(el.dataset.periodNumber))el.open=states.get(el.dataset.periodNumber)});
      const scrolls=[...current.querySelectorAll('.table-scroll')].map(el=>({left:el.scrollLeft,top:el.scrollTop}));
      const focused=document.activeElement;
      const link=focused?.closest('a[href],button[data-team-icon-viewer]');
      const focusKey=link&&current.contains(link)?{href:link.getAttribute('href'),slug:link.dataset.teamSlug}:null;
      const x=window.scrollX,y=window.scrollY;
      fresh.querySelectorAll('.standings-sortable').forEach(sortTable);
      const sortFocus=focused?.closest('.standings-sort');
      const resizeFocus=focused?.closest('.standings-resize');
      current.replaceWith(fresh);
      fresh.querySelectorAll('.standings-sortable').forEach(applyWidths);
      if(resizeFocus)fresh.querySelector(`.standings-resize[data-column="${resizeFocus.dataset.column}"]`)?.focus({preventScroll:true});
      if(sortFocus)fresh.querySelector(`.standings-sort[data-column="${sortFocus.dataset.column}"]`)?.focus({preventScroll:true});
      fresh.querySelectorAll('.table-scroll').forEach((el,i)=>{if(scrolls[i]){el.scrollLeft=scrolls[i].left;el.scrollTop=scrolls[i].top}});
      if(focusKey){
        const replacement=[...fresh.querySelectorAll('a[href],button[data-team-icon-viewer]')].find(el=>focusKey.slug?el.dataset.teamSlug===focusKey.slug:el.getAttribute('href')===focusKey.href);
        replacement?.focus({preventScroll:true});
      }
      window.scrollTo(x,y);
    }catch(error){
      const status=current.querySelector('.standings-refresh-status');
      if(status)status.textContent='Refresh delayed. Showing the last saved standings; retrying automatically.';
    }finally{
      clearTimeout(timeout);
      refreshing=false;
    }
  };
  setInterval(refresh,60000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh()});
})();
