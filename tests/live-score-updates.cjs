const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {
  constructor(){this.children=[];this.handlers={};this.hidden=false;this.attributes={};this.style={};}
  append(...nodes){this.children.push(...nodes)}
  prepend(node){this.children.unshift(node)}
  replaceChildren(...nodes){this.children=nodes}
  querySelector(selector){return this.children.find(node=>'.'+node.className===selector)||null}
  addEventListener(type,handler){this.handlers[type]=handler}
  setAttribute(key,value){this.attributes[key]=value}
  focus(){this.focused=true}
  getBoundingClientRect(){return {left:parseFloat(this.style.left)||950,top:parseFloat(this.style.top)||185,width:390,height:100}}
  setPointerCapture(id){this.pointer=id}
  hasPointerCapture(id){return this.pointer===id}
  releasePointerCapture(){this.pointer=null}
  closest(){return null}
}
const player=(key,points,stats={},extra={})=>({key,points,stats,team:'Lone Tsar',name:'Dubois, Pierre-Luc',nhl:'WSH',goalie:false,...extra});
const state=players=>({date:'2026-10-07',players});
const before=state([player('a',1,{A:1}),player('b',0),player('c',0,{}, {team:'Young Guns',goalie:true,name:'Askarov, Yaroslav',nhl:'SJS'})]);
const next=state([player('a',2,{A:2}),player('b',2,{G:1,PPG:1},{name:'Eichel, Jack',nhl:'VGK'}),player('c',5,{W:1,SO:1},{team:'Young Guns',goalie:true,name:'Askarov, Yaroslav',nhl:'SJS'})]);
const elements=Object.fromEntries(['live-score-updates','live-score-updates-list','live-score-updates-toggle','live-score-updates-close','live-score-updates-count','live-score-updates-handle'].map(id=>[id,new Node()]));
const panel=data=>({dataset:{scoringState:JSON.stringify(data)}});
const windowEvents={};
const context={window:{innerWidth:1363,innerHeight:936,addEventListener(type,fn){windowEvents[type]=fn}},document:{getElementById:id=>elements[id],querySelector:selector=>selector==='.current-matchup-list'?panel(before):null,createElement:()=>new Node()},Map,Date};
vm.runInNewContext(fs.readFileSync('public/live-score-updates.js','utf8'),context);
const alerts=context.window.EcfhlScoreUpdates;
const changes=alerts.compare(before,next);
assert.equal(changes.length,3,'Include all scoring players and teams in the same refresh.');
assert.equal(changes[0].points,1);assert.equal(changes[0].stats,'1 assist','Report the new assist, not the prior total.');
assert.equal(changes[1].stats,'1 goal · 1 PPG');assert.equal(changes[2].stats,'1 win · 1 shutout');
assert.equal(alerts.compare(next,next).length,0,'Repeated snapshots must not repeat alerts.');
assert.equal(alerts.compare(null,next).length,0,'No flood of old scores on first load.');
assert.equal(alerts.compare(before,{...next,date:'2026-10-08'}).length,0,'Day changes establish a new baseline.');
assert.equal(alerts.compare(next,before).length,0,'Corrections reducing points are not scoring plays.');
assert.equal(alerts.compare(before,state([player('new',8,{G:3})])).length,0,'Newly appearing lineup entries are not new scoring plays.');
assert.equal(alerts.compare(state([player('a',0)]),state([player('a',.5,{A:1})]))[0].points,.5);
const ui=alerts.start();ui.update(panel(before));assert.equal(elements['live-score-updates-list'].children.length,0);
ui.update(panel(next));
assert.equal(elements['live-score-updates-count'].textContent,'3');assert.equal(elements['live-score-updates'].hidden,false);
assert.equal(elements['live-score-updates-toggle'].attributes['data-has-updates'],'true','The button must glow when updates exist.');
const handle=elements['live-score-updates-handle'],tray=elements['live-score-updates'];
handle.handlers.pointerdown({button:0,pointerId:1,clientX:1000,clientY:190,target:handle});
handle.handlers.pointermove({pointerId:1,clientX:850,clientY:230});
assert.equal(tray.style.left,'800px');assert.equal(tray.style.top,'225px');
handle.handlers.pointerup({pointerId:1});assert.equal(tray.attributes['data-dragging'],'false');
handle.handlers.keydown({key:'ArrowDown',target:handle,preventDefault(){}});assert.equal(tray.style.top,'235px');
handle.handlers.pointerdown({button:0,pointerId:2,clientX:850,clientY:240,target:handle});
handle.handlers.pointermove({pointerId:2,clientX:-2000,clientY:-2000});
assert.equal(tray.style.left,'8px');assert.equal(tray.style.top,'8px','Dragging cannot move the header off screen.');
handle.handlers.pointercancel({pointerId:2});
handle.handlers.pointerdown({button:0,pointerId:3,clientX:10,clientY:10,target:{closest:()=>elements['live-score-updates-close']}});
handle.handlers.pointermove({pointerId:3,clientX:1000,clientY:1000});assert.equal(tray.style.left,'8px','Close button must not start dragging.');
const batch=elements['live-score-updates-list'].children[0];
assert.equal(batch.children.length,3,'One time label and one group per team.');
assert.equal(batch.children[1].children[0].children[1].textContent,'+3 FPts');
assert.equal(batch.children[2].children[0].children[1].textContent,'+5 FPts');
elements['live-score-updates-close'].handlers.click();assert.equal(elements['live-score-updates'].hidden,true);
ui.update(panel(next));assert.equal(elements['live-score-updates'].hidden,true,'Unchanged scores must not reopen a dismissed popup.');
elements['live-score-updates-toggle'].handlers.click();assert.equal(elements['live-score-updates'].hidden,false);
ui.update(panel(state([player('a',3,{A:3}),...next.players.slice(1)])));
assert.equal(elements['live-score-updates-count'].textContent,'4');assert.equal(elements['live-score-updates-list'].children.length,2);
ui.update({dataset:{scoringState:'broken'}});assert.equal(elements['live-score-updates-count'].textContent,'4');
ui.update(panel({...next,date:'2026-10-08'}));
assert.equal(elements['live-score-updates-list'].children[0].textContent,'No updates');
assert.equal(elements['live-score-updates-toggle'].attributes['data-has-updates'],'false','Clear the glow when a new date clears the updates.');
console.log('Scoring popup checks passed: grouped gains, stat deltas, duplicates/corrections, dismiss/reopen, orange update state, pointer/keyboard movement, viewport bounds, cancel and close-button isolation.');
