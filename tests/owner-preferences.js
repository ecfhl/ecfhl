// Exercise preference-saving interactions without requiring browser push support.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class Element {
 constructor(properties={}){Object.assign(this,{listeners:{},dataset:{},disabled:false,checked:false},properties);}
 addEventListener(name,callback){(this.listeners[name]??=[]).push(callback);}
 async fire(name){for(const callback of this.listeners[name]||[])await callback({preventDefault(){}});}
}
const own=new Element({name:'own_goalies'}),scores=new Element({name:'team_scores',checked:true});
const watches=[new Element({name:'goalies[]',value:'MTL|samgoalie'}),new Element({name:'goalies[]',value:'MTL|samgoalie'})];
const save=new Element(),status=new Element(),enable=new Element(),disable=new Element(),pushState=new Element();
const form=new Element({action:'/notifications',querySelectorAll:()=>[own,scores,...watches]});
const ids={'owner-preferences-form':form,'owner-save-preferences':save,'owner-save-state':status,'owner-enable-push':enable,'owner-disable-push':disable,'owner-push-state':pushState};
const calls=[],redirects=[];let finish;
const context={document:{getElementById:id=>ids[id],querySelectorAll:()=>watches,querySelector:()=>({content:'test-csrf'})},navigator:{},window:{location:{assign:url=>redirects.push(url)}},
 FormData:class {getAll(name){return watches.filter(input=>input.name===name&&input.checked).map(input=>input.value);}},
 fetch:(url,options)=>{calls.push({url,options});return new Promise(resolve=>{finish=resolve;});},Set,JSON,Boolean,Error,Uint8Array};
vm.runInNewContext(fs.readFileSync('public/owner-notifications.js','utf8'),context);
(async()=>{
 assert.equal(save.disabled,true,'Saved settings must start disabled');
 own.checked=true;await form.fire('change');assert.equal(save.disabled,false);
 own.checked=false;await form.fire('change');assert.equal(save.disabled,true,'Reverted settings are already saved');
 watches[0].checked=true;await watches[0].fire('change');await form.fire('change');
 assert.equal(watches[1].checked,true,'Today/tomorrow copies must sync');
 assert.equal(save.disabled,false,'Goalie selection must enable saving');
 const pending=form.fire('submit');assert.equal(save.disabled,true);assert.equal(save.textContent,'Saving…');
 await form.fire('submit');assert.equal(calls.length,1,'Repeated submits must not duplicate requests');
 const submitted=JSON.parse(calls[0].options.body);assert.deepEqual(submitted.goalies,['MTL|samgoalie']);
 assert.equal(calls[0].options.headers['X-CSRF-TOKEN'],'test-csrf');
 own.checked=true;await form.fire('change');
 finish({ok:true,json:async()=>({preferences:submitted})});await pending;
 assert.equal(save.disabled,false,'Changes during saving must remain unsaved');
 assert.equal(status.textContent,'Unsaved changes.');
 assert.equal(redirects.length,0,'Do not leave behind changes made during the save');
 const failed=form.fire('submit');finish({ok:false,json:async()=>({errors:{goalies:['Refresh the goalie list.']}})});await failed;
 assert.equal(save.disabled,false,'Failed saves must allow retry');assert.equal(status.dataset.state,'error');
 assert.equal(status.textContent,'Refresh the goalie list.');
 assert.equal(redirects.length,0,'Failed saves must stay on the page');
 const unconfirmed=form.fire('submit');finish({ok:true,json:async()=>({})});await unconfirmed;
 assert.equal(save.disabled,false,'Unconfirmed saves must allow retry');assert.equal(redirects.length,0);
 const retry=form.fire('submit');finish({ok:true,json:async()=>({preferences:JSON.parse(calls[3].options.body),redirect_url:'/teams/current/alpha'})});await retry;
 assert.equal(save.disabled,true,'Successful saves stay locked while navigating');
 assert.deepEqual(redirects,['/teams/current/alpha'],'Successful save must open the owner team');
 assert.equal(status.textContent,'Saved. Opening your team…');
 await form.fire('submit');assert.equal(calls.length,4,'Do not submit again during navigation');
 console.log('Preference UI checks passed: initial saved state, change/revert, duplicate goalies, saving lock, concurrent edits, error/unconfirmed responses, retry and redirect after confirmed save.');
})().catch(error=>{console.error(error);process.exitCode=1;});

// Push controls stay locked during setup, requests and the three-second cooldown.
(async()=>{
 const enable=new Element({textContent:'Enable notifications'}),disable=new Element({textContent:'Disable on this device',hidden:true}),state=new Element();
 const ids={'owner-enable-push':enable,'owner-disable-push':disable,'owner-push-state':state};
 const timers=new Map(),requests=[];let timerId=0,sub=null,active=false,permission='granted',ready;
 const registration={pushManager:{getSubscription:async()=>sub,subscribe:async()=>sub={endpoint:'test-endpoint',unsubscribe:async()=>{sub=null;return true;}}},active:{postMessage:(message,ports)=>{if(ports)ports[0].channel.port1.onmessage();}}};
 const context={document:{getElementById:id=>ids[id],querySelectorAll:()=>[],querySelector:()=>({content:'test-csrf'})},navigator:{serviceWorker:{register:()=>new Promise(resolve=>{ready=resolve;}),ready:Promise.resolve(registration)}},window:{PushManager:class {},Notification:{}},
  Notification:{permission:'default',requestPermission:async()=>permission},
  MessageChannel:class {constructor(){this.port1={};this.port2={channel:this};}},
  localStorage:{setItem(){},removeItem(){}},atob:s=>Buffer.from(s,'base64').toString('binary'),
  setTimeout:(callback,delay)=>{timers.set(++timerId,{callback,delay});return timerId;},clearTimeout:id=>timers.delete(id),
  fetch:async(url)=>{requests.push(url);if(url==='/push/subscribe')active=true;if(url==='/push/unsubscribe')active=false;return {ok:true,json:async()=>url==='/push/config'?{publicKey:'YQ'}:url==='/push/device'?{enabled:active}:{feedToken:'token',latestId:1}};}
 };
 vm.runInNewContext(fs.readFileSync('public/owner-notifications.js','utf8'),context);
 assert.equal(enable.disabled,true,'Push setup must disable controls');await enable.fire('click');assert.equal(requests.length,0);
 ready();await new Promise(setImmediate);assert.equal(enable.disabled,false);
 const enabling=enable.fire('click');assert.equal(enable.disabled,true);assert.equal(disable.disabled,true);await enable.fire('click');await enabling;
 assert.equal(requests.filter(url=>url==='/push/subscribe').length,1);
 assert.equal(disable.hidden,false);assert.equal(disable.disabled,true,'Reverse action must wait three seconds');
 await disable.fire('click');assert.equal(requests.includes('/push/unsubscribe'),false);
 const cooldown=()=>{const entry=[...timers].find(([,timer])=>timer.delay===3000);assert.ok(entry,'A three-second cooldown is required');timers.delete(entry[0]);entry[1].callback();};
 cooldown();assert.equal(disable.disabled,false);await disable.fire('click');assert.equal(enable.hidden,false);assert.equal(enable.disabled,true);cooldown();
 permission='denied';await enable.fire('click');assert.equal(enable.disabled,false,'Permission failure must allow retry');assert.equal(timers.size,0);
 console.log('Push UI checks passed: setup lock, duplicate-click protection, three-second cooldown in both directions and retry after failure.');
})().catch(error=>{console.error(error);process.exitCode=1;});
