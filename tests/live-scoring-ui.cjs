const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
function setup(){
 const oldCards=[{dataset:{matchupKey:'a'},open:true},{dataset:{matchupKey:'b'},open:false}],newCards=[{dataset:{matchupKey:'a'},open:false},{dataset:{matchupKey:'b'},open:true}];
 const fresh={querySelectorAll:()=>newCards};
 const old={querySelectorAll:()=>oldCards,contains:()=>false,replaceWith(value){assert.equal(value,fresh);this.replaced=true}};
 const warning=[],status={querySelector:()=>warning.length?warning[0]:null,append(w){warning.push(w)}};
 const handlers={},document={hidden:false,activeElement:{},querySelector:s=>s==='.current-matchup-list'?old:s==='.team-updated'?status:s==='dialog[open],#team-icon-modal.open'&&document.modal?{}:null,createElement:()=>({dataset:{}}),addEventListener(type,fn){handlers[type]=fn}};
 const window={scrollX:12,scrollY:620,scrollTo(x,y){assert.equal(x,12);assert.equal(y,620);this.restored=true}};
 let calls=0,release,ok=true,missing=false,cleared=false,pinned=0,saved=0,tick;
 const fetch=async(url,opts)=>{calls++;assert.equal(opts.cache,'no-store');assert.equal(opts.headers.Accept,'text/html');await new Promise(r=>release=r);return{ok,text:async()=>'<html>fresh</html>'}};
 const context={window,document,location:{href:'https://ecfhl.example/teams/current'},fetch,AbortController,Map,DOMParser:class{parseFromString(){return{querySelector:s=>s==='.current-matchup-list'&&!missing?fresh:null}}},setInterval(fn,ms){assert.equal(ms,60000);tick=fn},setTimeout(){return 1},clearTimeout(){cleared=true}};
 vm.runInNewContext(fs.readFileSync('public/live-scoring.js','utf8'),context);
 const refresh=window.EcfhlLiveScoring.start({saveOpenMatchups(){saved++},highlightNotificationTeam(){pinned++}});
 return{old,document,window,status,oldCards,newCards,handlers,warning,refresh,get calls(){return calls},get cleared(){return cleared},get pinned(){return pinned},get saved(){return saved},release(){release()},fail(){ok=false},missing(){missing=true}};
}
(async()=>{
 const ui=setup();const pending=ui.refresh();await ui.refresh();assert.equal(ui.calls,1);ui.oldCards[1].open=true;ui.release();await pending;
 assert.ok(ui.old.replaced&&ui.window.restored&&ui.cleared);assert.deepEqual(ui.newCards.map(c=>c.open),[true,true]);assert.equal(ui.pinned,1);assert.equal(ui.saved,1);
 const fail=setup();fail.fail();let work=fail.refresh();fail.release();await work;assert.equal(fail.old.replaced,undefined);assert.match(fail.warning[0].textContent,/retrying automatically/);work=fail.refresh();fail.release();await work;assert.equal(fail.calls,2);assert.equal(fail.warning.length,1);
 const missing=setup();missing.missing();work=missing.refresh();missing.release();await work;assert.equal(missing.old.replaced,undefined);
 const modal=setup();modal.document.modal=true;await modal.refresh();assert.equal(modal.calls,0);modal.document.modal=false;work=modal.refresh();modal.document.modal=true;modal.release();await work;assert.equal(modal.old.replaced,undefined);
 const hidden=setup();hidden.document.hidden=true;await hidden.refresh();assert.equal(hidden.calls,0);
 console.log('Live Scoring UI checks passed: in-place refresh, response-time expansion state, scroll preservation, own-matchup pinning, overlap guard, failure retry and open-dialog preservation.');
})().catch(e=>{console.error(e);process.exit(1)});
