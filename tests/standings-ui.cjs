const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
function setup(){
 const oldPeriods=[{dataset:{periodNumber:'1'},open:true},{dataset:{periodNumber:'2'},open:false}],newPeriods=[{dataset:{periodNumber:'1'},open:false},{dataset:{periodNumber:'2'},open:true}],oldScroll={scrollLeft:120,scrollTop:30},newScroll={scrollLeft:0,scrollTop:0},status={};
 const fresh={querySelectorAll:s=>s==='.standings-period'?newPeriods:s==='.table-scroll'?[newScroll]:[]};
 const old={querySelectorAll:s=>s==='.standings-period'?oldPeriods:s==='.table-scroll'?[oldScroll]:[],querySelector:()=>status,contains:()=>false,replaceWith(value){assert.equal(value,fresh);this.replaced=true}};
 const handlers={},document={hidden:false,querySelector:s=>s==='.standings-page'?old:document.modal||(document.playerPopup&&s.includes('dialog[open]'))?{}:null,addEventListener(type,fn){handlers[type]=fn}};
 const window={location:{href:'https://ecfhl.example/standings?season_type=h2h'},scrollX:0,scrollY:580,scrollTo(x,y){assert.equal(x,0);assert.equal(y,580);this.restored=true}};
 let tick,calls=0,release,ok=true,missing=false,timerCleared=false;
 const fetch=async(url,opts)=>{calls++;assert.equal(url,window.location.href);assert.equal(opts.cache,'no-store');assert.equal(opts.headers.Accept,'text/html');await new Promise(r=>release=r);return{ok,text:async()=>'<html>fresh</html>'}};
 const context={document,window,fetch,AbortController,Map,DOMParser:class{parseFromString(){return{querySelector:()=>missing?null:fresh}}},setInterval(fn,ms){assert.equal(ms,60000);tick=fn},setTimeout(){return 1},clearTimeout(){timerCleared=true}};
 vm.runInNewContext(fs.readFileSync('public/standings.js','utf8'),context);
 return{old,document,window,status,oldPeriods,newPeriods,newScroll,handlers,tick,get calls(){return calls},get timerCleared(){return timerCleared},release(){release()},fail(){ok=false},missing(){missing=true}};
}
(async()=>{
 const ui=setup(),pending=ui.tick();await ui.tick();assert.equal(ui.calls,1);ui.oldPeriods[1].open=true;ui.release();await pending;
 assert.ok(ui.old.replaced&&ui.window.restored&&ui.timerCleared);assert.equal(ui.newPeriods[0].open,true);assert.equal(ui.newPeriods[1].open,true);assert.equal(ui.newScroll.scrollLeft,120);assert.equal(ui.newScroll.scrollTop,30);
 const fail=setup();fail.fail();let work=fail.tick();fail.release();await work;assert.equal(fail.old.replaced,undefined);assert.match(fail.status.textContent,/retrying automatically/);work=fail.tick();assert.equal(fail.calls,2);fail.release();await work;
 const absent=setup();absent.missing();work=absent.tick();absent.release();await work;assert.equal(absent.old.replaced,undefined);
 const hidden=setup();hidden.document.hidden=true;await hidden.tick();assert.equal(hidden.calls,0);hidden.document.hidden=false;work=hidden.handlers.visibilitychange();assert.equal(hidden.calls,1);hidden.release();await new Promise(r=>setImmediate(r));
 const modal=setup();modal.document.modal=true;await modal.tick();assert.equal(modal.calls,0);modal.document.modal=false;work=modal.tick();modal.document.modal=true;modal.release();await work;assert.equal(modal.old.replaced,undefined);
 const playerPopup=setup();playerPopup.document.playerPopup=true;await playerPopup.tick();assert.equal(playerPopup.calls,0);playerPopup.document.playerPopup=false;work=playerPopup.tick();playerPopup.document.playerPopup=true;playerPopup.release();await work;assert.equal(playerPopup.old.replaced,undefined);
 console.log('Standings UI checks passed: minute refresh, preserved expanded periods and scroll, duplicate-request guard, safe failures/retry, visibility and logo-viewer preservation.');
})().catch(e=>{console.error(e);process.exit(1)});

// Sorting uses exact scores, keeps missing values last, and preserves team rows.
{
 const handlers={};
 const rows=[['Zulu','1,001',1000.6],['alpha','1,001',1000.9],['Bravo','—','']].map(([name,display,exact])=>({cells:[{dataset:{},textContent:'1'},{dataset:{},textContent:name},...Array.from({length:4},()=>({dataset:{},textContent:'0'})),{dataset:{sortValue:String(exact)},textContent:display}]}));
 const body={rows,append(row){this.rows=this.rows.filter(r=>r!==row);this.rows.push(row)}};
 const buttons=[1,6].map(column=>({dataset:{column:String(column),defaultDirection:column===1?'asc':'desc'},header:{setAttribute(k,v){this[k]=v}},arrow:{},closest(selector){return selector==='th'?this.header:table},querySelector(){return this.arrow}}));
 const table={tBodies:[body],dataset:{},querySelectorAll:()=>buttons};
 const document={addEventListener(type,fn){handlers[type]=fn}};
 vm.runInNewContext(fs.readFileSync('public/standings.js','utf8'),{document,setInterval(){}});
 const click=button=>handlers.click({target:{closest:()=>button}});
 click(buttons[1]);assert.equal(body.rows[0].cells[1].textContent,'alpha');assert.equal(body.rows[2].cells[1].textContent,'Bravo');assert.equal(buttons[1].header['aria-sort'],'descending');
 click(buttons[1]);assert.equal(body.rows[0].cells[1].textContent,'Zulu');assert.equal(body.rows[2].cells[1].textContent,'Bravo');
 click(buttons[0]);assert.deepEqual(body.rows.map(row=>row.cells[1].textContent),['alpha','Bravo','Zulu']);
 click(buttons[0]);assert.deepEqual(body.rows.map(row=>row.cells[1].textContent),['Zulu','Bravo','alpha']);
 console.log('Standings sorting checks passed: exact numeric scores, missing values, team names and direction toggles.');
}

{
 const handlers={};
 const style=()=>({values:{},setProperty(key,value){this.values[key]=value}});
 const handles=[0,1].map(column=>({dataset:{column:String(column)},setAttribute(key,value){this[key]=value},closest:()=>table,setPointerCapture(id){this.id=id},hasPointerCapture(id){return this.id===id},releasePointerCapture(){this.id=null}}));
 const headers=handles.map(handle=>({style:style(),getBoundingClientRect:()=>({width:100}),querySelector:()=>handle}));
 const cells=headers.map(()=>({style:style()}));
 const table={dataset:{},style:style(),tHead:{rows:[{cells:headers}]},tBodies:[{rows:[{cells}]}]};
 vm.runInNewContext(fs.readFileSync('public/standings.js','utf8'),{document:{addEventListener(type,fn){handlers[type]=fn}},setInterval(){}});
 const event=extra=>({target:{closest:()=>handles[0]},preventDefault(){},...extra});
 handlers.pointerdown(event({button:0,pointerId:1,clientX:100}));
 handlers.pointermove(event({pointerId:2,clientX:180}));assert.equal(headers[0].style.values.width,'100px');
 handlers.pointermove(event({pointerId:1,clientX:180}));assert.equal(headers[0].style.values.width,'180px');assert.equal(cells[0].style.values.width,'180px');assert.equal(table.style.values.width,'280px');
 handlers.pointermove(event({pointerId:1,clientX:-1000}));assert.equal(headers[0].style.values.width,'48px');
 handlers.pointerup(event({pointerId:1}));assert.equal(table.dataset.resizing,'false');assert.equal(handles[0].id,null);
 handlers.keydown(event({key:'ArrowRight',shiftKey:true}));assert.equal(headers[0].style.values.width,'78px');assert.equal(handles[0]['aria-valuenow'],'78');
 console.log('Column resizing checks passed: pointer capture, width limits, row alignment and keyboard resizing.');
}
