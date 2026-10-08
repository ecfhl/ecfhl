const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {
 constructor(){this.dataset={};this.children=[];this.handlers={};this.hidden=false;this.attributes={};this.style={};}
 append(...nodes){this.children.push(...nodes)}replaceChildren(...nodes){this.children=nodes}
 addEventListener(type,handler){this.handlers[type]=handler}setAttribute(key,value){this.attributes[key]=value}focus(){this.focused=true}
 getBoundingClientRect(){return {left:parseFloat(this.style.left)||950,top:parseFloat(this.style.top)||185,width:390,height:100}}
 querySelectorAll(){return this.children.map(label=>label.children[0]).filter(input=>input.checked)}
 setPointerCapture(id){this.pointer=id}hasPointerCapture(id){return this.pointer===id}releasePointerCapture(){this.pointer=null}closest(){return null}
}
const filters=new Map(),localStorage={getItem:k=>filters.get(k)||null,setItem:(k,v)=>filters.set(k,v)};
const store=new Map(),sessionStorage={getItem:k=>store.get(k)||null,setItem:(k,v)=>store.set(k,v)};
function setup(){
 const elements=Object.fromEntries(['live-score-updates',...['list','toggle','close','minimize','count','handle','trash','restore','scope','team','team-summary','team-label'].map(s=>'live-score-updates-'+s),'communication-context'].map(id=>[id,new Node()]));
 elements['live-score-updates-team'].dataset.accountTeam='1';elements['communication-context'].dataset.userId='10';
 const context={sessionStorage,localStorage,Event:class{},window:{innerWidth:1363,innerHeight:936,addEventListener(){},dispatchEvent(){}},document:{getElementById:id=>elements[id],querySelector:()=>null,createElement:()=>new Node()}};
 vm.runInNewContext(fs.readFileSync('public/live-score-updates.js','utf8'),context);
 return {elements,api:context.window.EcfhlScoreUpdates,start:()=>context.window.EcfhlScoreUpdates.start()};
}
const p=(key,points,stats={},extra={})=>({key,points,stats,teamId:'1',team:'Our team',name:'Player',nhl:'WSH',goalie:false,change:'up',...extra});
const state=players=>({date:'2026-10-07',players,teams:[{id:'1',name:'Our team'},{id:'2',name:'Opponent'},{id:'3',name:'Other'}],matchups:[{away_team_id:'1',home_team_id:'2'}]});
const before=state([p('a',0),p('b',0,{}, {teamId:'2'}),p('c',0,{}, {teamId:'3'})]);
const next=state([p('a',2,{G:1}),p('b',1,{A:1},{teamId:'2'}),p('c',5,{W:1,SO:1},{teamId:'3',goalie:true})]);
let app=setup(),ui=app.start(),e=app.elements;
assert.equal(app.start(),ui,'One scoring panel per page');
assert.equal(app.api.compare(before,next).length,3);assert.equal(app.api.compare(null,next).length,0);assert.equal(app.api.compare(next,next).length,0);assert.equal(app.api.compare(next,before).length,0);
assert.equal(app.api.compare(before,next)[0].stats,'1 goal');assert.equal(app.api.compare(before,next)[2].stats,'1 win · 1 shutout');
ui.update(before);assert.equal(e['live-score-updates-count'].textContent,'');assert.equal(e['live-score-updates-count'].hidden,true);
ui.update(next);assert.equal(e['live-score-updates-count'].textContent,'2','Default matchup excludes other league teams');
assert.equal(e['live-score-updates'].dataset.minimized,'true');
e['live-score-updates-toggle'].handlers.click();assert.equal(e['live-score-updates'].dataset.minimized,'false');
e['live-score-updates-close'].handlers.click();assert.equal(e['live-score-updates'].hidden,true);
e['live-score-updates-toggle'].handlers.click();assert.equal(e['live-score-updates'].hidden,false);
const later=state([p('a',4,{G:2}),...next.players.slice(1)]);ui.update(later);assert.equal(e['live-score-updates-count'].textContent,'3');
e['live-score-updates-minimize'].handlers.click();assert.equal(e['live-score-updates'].dataset.minimized,'true');
// A new page restores the baseline, content, enabled setting and minimized mode.
app=setup();ui=app.start();e=app.elements;assert.equal(e['live-score-updates-count'].textContent,'3');assert.equal(e['live-score-updates'].dataset.minimized,'true');ui.update(later);assert.equal(e['live-score-updates-count'].textContent,'3','Navigation does not duplicate events');
e['live-score-updates-trash'].handlers.click();assert.equal(e['live-score-updates-trash'].disabled,true);assert.equal(e['live-score-updates-count'].textContent,'');
e['live-score-updates-scope'].value='team';e['live-score-updates-scope'].handlers.change();ui.update(state([p('a',6,{G:3}),p('b',2,{A:2},{teamId:'2'}),later.players[2]]));assert.equal(e['live-score-updates-count'].textContent,'1');
e['live-score-updates-scope'].value='teams';e['live-score-updates-team'].children[2].children[0].checked=true;e['live-score-updates-team'].handlers.change();e['live-score-updates-scope'].handlers.change();ui.update(state([p('a',6,{G:3}),p('b',2,{A:2},{teamId:'2'}),p('c',6,{W:2},{teamId:'3'})]));assert.equal(e['live-score-updates-count'].textContent,'1','Specific teams filters selected teams');assert.equal(e['live-score-updates-team-label'].hidden,false);
e['live-score-updates-scope'].value='league';e['live-score-updates-scope'].handlers.change();ui.update(state([p('a',8,{G:4}),p('b',3,{A:3},{teamId:'2'}),p('c',7,{W:2},{teamId:'3'})]));assert.equal(e['live-score-updates-count'].textContent,'3');
e['live-score-updates-close'].handlers.click();ui.update(state([p('a',10,{G:5}),p('b',3,{A:3},{teamId:'2'}),p('c',7,{W:2},{teamId:'3'})]));assert.equal(e['live-score-updates'].hidden,false);assert.equal(e['live-score-updates'].dataset.minimized,'true');
e['live-score-updates-scope'].value='nhl';e['live-score-updates-scope'].handlers.change();const nhl={key:'goal1',team:'WSH',player:'Scorer',stats:'Goal',points:0};ui.update({...next,nhlEvents:[nhl]});assert.equal(e['live-score-updates-count'].textContent,'');ui.update({...next,nhlEvents:[nhl,{...nhl,key:'goal2'}]});assert.equal(e['live-score-updates-count'].textContent,'1');ui.update({...next,nhlEvents:null});ui.update({...next,nhlEvents:[nhl,{...nhl,key:'goal2'}]});assert.equal(e['live-score-updates-count'].textContent,'1');
e['live-score-updates-toggle'].handlers.click();const handle=e['live-score-updates-handle'];handle.handlers.pointerdown({button:0,pointerId:1,clientX:1000,clientY:190,target:handle});handle.handlers.pointermove({pointerId:1,clientX:-2000,clientY:-2000});assert.equal(e['live-score-updates'].style.left,'8px');handle.handlers.pointerup({pointerId:1});
ui.update({...next,date:'2026-10-08'});assert.equal(e['live-score-updates-count'].textContent,'');
ui.setEnabled(false);assert.equal(ui.enabled(),false);ui.update({...next,date:'2026-10-08',players:[p('a',20)]});assert.equal(e['live-score-updates-count'].textContent,'','Disabled updates are suppressed');
app=setup();ui=app.start();e=app.elements;assert.equal(ui.enabled(),false,'Off setting survives reload');
e['live-score-updates-scope'].value='league';e['live-score-updates-scope'].handlers.change();ui.setEnabled(true);ui.update({...next,date:'2026-10-08',players:[p('a',20)]});assert.equal(e['live-score-updates-count'].textContent,'','Re-enabling establishes a fresh baseline');ui.update({...next,date:'2026-10-08',players:[p('a',22,{G:1})]});assert.equal(e['live-score-updates-count'].textContent,'1');
console.log('Scoring popup checks passed: one-click reopen, on/off updates, navigation persistence, specific teams and all scopes, controls and dragging.');

