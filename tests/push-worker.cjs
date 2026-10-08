const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
function worker(){
 const state=new Map([['ownerFeedToken','owner-a'],['lastNotificationId',0]]),handlers={},shown=[],options=[],opened=[];
 let opens=0,feed=[{id:1,title:'One'},{id:2,title:'Two'}],release,blocked=false,failId=null;
 const db={transaction(){const tx={objectStore(){return{
  get(key){const req={};queueMicrotask(()=>{req.result=state.get(key);req.onsuccess()});return req},
  put(value,key){state.set(key,value);queueMicrotask(()=>tx.oncomplete?.())}
 }} };return tx}};
 const indexedDB={open(){opens++;const req={};queueMicrotask(()=>{req.result=db;req.onsuccess()});return req}};
 const fetch=async(url,opts)=>{if(blocked)await new Promise(resolve=>release=resolve);const after=Number(new URL(url,'https://ecfhl.example').searchParams.get('after'));assert.equal(opts.cache,'no-store');return {ok:true,json:async()=>({notifications:feed.filter(n=>n.id>after).slice(0,100)})}};
 const self={addEventListener:(name,fn)=>handlers[name]=fn,registration:{async showNotification(title,opts){const id=Number(opts.tag.replace('ecfhl-',''));if(id===failId)throw Error('Temporary display failure');shown.push(id);options.push(opts)}},skipWaiting(){},location:{origin:'https://ecfhl.example'}};
 vm.runInNewContext(fs.readFileSync('public/push-sw.js','utf8'),{self,indexedDB,fetch,URL,AbortController,setTimeout,clearTimeout,Promise,Number,encodeURIComponent,clients:{matchAll:async()=>[],openWindow:async url=>opened.push(url)}});
 const trigger=(type,data,ports=[])=>{let work;handlers[type]({data,ports,waitUntil(p){work=p}});return work};
 return {state,shown,options,opened,click:async(action,data)=>{let pending;handlers.notificationclick({action,notification:{data,close(){throw Error("Notifications must remain visible after a tap")}},waitUntil(p){pending=p}});await pending;},push:()=>trigger('push'),feed:(token,lastId)=>trigger('message',{type:'set-owner-feed',token,lastId},[{postMessage(){}}]),setFeed:value=>feed=value,block(){blocked=true},release(){blocked=false;release()},fail:id=>failId=id,get opens(){return opens}};
}
(async()=>{
 const concurrent=worker();await Promise.all([concurrent.push(),concurrent.push(),concurrent.push()]);assert.deepEqual(concurrent.shown,[1,2]);assert.equal(concurrent.state.get('lastNotificationId'),2);assert.equal(concurrent.opens,1,'Reuse one IndexedDB connection per service-worker lifetime.');
 const pages=worker();pages.setFeed(Array.from({length:250},(_,i)=>({id:i+1,title:'Score'})));await pages.push();assert.equal(pages.shown.length,250);assert.equal(pages.state.get('lastNotificationId'),250);
 const failure=worker();failure.fail(2);await assert.rejects(failure.push());assert.deepEqual(failure.shown,[1]);assert.equal(failure.state.get('lastNotificationId'),1);failure.fail(null);await failure.push();assert.deepEqual(failure.shown,[1,2]);
 const changed=worker();changed.block();const pending=changed.push();await new Promise(r=>setImmediate(r));const switchOwner=changed.feed('owner-b',100);changed.release();await Promise.all([pending,switchOwner]);assert.deepEqual(changed.shown,[],'A late fetch from the previous account must not display alerts.');assert.equal(changed.state.get('ownerFeedToken'),'owner-b');assert.equal(changed.state.get('lastNotificationId'),100);
 await changed.feed('',0);await changed.push();assert.deepEqual(changed.shown,[]);
 const reply=worker();reply.setFeed([{id:1,category:'private-message',url:'/messages?user_id=7',title:'Private'},{id:2,category:'league-message',url:'/messages',title:'League'},{id:3,category:'live-score',url:'/teams/current',title:'Score'}]);await reply.push();
 assert.equal(reply.options[0].actions[0].action,'reply');assert.equal(reply.options[2].actions,undefined);
 await reply.click('reply',reply.options[0].data);assert.equal(reply.opened[0],'https://ecfhl.example/messages?user_id=7&reply=1');
 await reply.click('reply',reply.options[1].data);assert.equal(reply.opened[1],'https://ecfhl.example/messages?reply=1');
 await reply.click('',reply.options[0].data);assert.equal(reply.opened[2],'https://ecfhl.example/messages?user_id=7');
 console.log('Push worker checks passed: simultaneous wakeups, feed pagination, failure recovery, account changes, disabling and IndexedDB reuse.');
})().catch(error=>{console.error(error);process.exit(1)});
