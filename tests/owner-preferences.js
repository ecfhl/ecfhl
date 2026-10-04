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
const calls=[];let finish;
const context={document:{getElementById:id=>ids[id],querySelectorAll:()=>watches,querySelector:()=>({content:'test-csrf'})},navigator:{},window:{},
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
 const second=form.fire('submit');const saved=JSON.parse(calls[1].options.body);
 finish({ok:true,json:async()=>({preferences:saved})});await second;
 assert.equal(save.disabled,true);assert.equal(status.textContent,'Preferences saved.');
 scores.checked=false;await form.fire('change');assert.equal(save.disabled,false);
 const failed=form.fire('submit');finish({ok:false,json:async()=>({errors:{goalies:['Refresh the goalie list.']}})});await failed;
 assert.equal(save.disabled,false,'Failed saves must allow retry');assert.equal(status.dataset.state,'error');
 assert.equal(status.textContent,'Refresh the goalie list.');
 const retry=form.fire('submit');finish({ok:true,json:async()=>({preferences:JSON.parse(calls[3].options.body)})});await retry;
 assert.equal(save.disabled,true,'Successful retry must return to saved state');
 console.log('Preference UI checks passed: initial saved state, change/revert, duplicate goalies, saving lock, concurrent edits, confirmation, error and retry, without browser push support.');
})().catch(error=>{console.error(error);process.exitCode=1;});
