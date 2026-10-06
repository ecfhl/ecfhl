const DB_NAME='ecfhl-push';
const STORE='state';
const KEY='lastNotificationId';
const TEAM_KEY='notificationTeamId';
const FEED_KEY='ownerFeedToken';

let database;
function openDb(){
  return database ||= new Promise((resolve,reject)=>{
    const req=indexedDB.open(DB_NAME,1);
    req.onupgradeneeded=()=>req.result.createObjectStore(STORE);
    req.onsuccess=()=>resolve(req.result);
    req.onerror=()=>{database=undefined;reject(req.error)};
  });
}

async function getLastId(){
  const db=await openDb();
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(STORE,'readonly');
    const req=tx.objectStore(STORE).get(KEY);
    req.onsuccess=()=>resolve(Number(req.result||0));
    req.onerror=()=>reject(req.error);
  });
}

async function getNotificationTeamId(){
  const db=await openDb();
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(STORE,'readonly');
    const req=tx.objectStore(STORE).get(TEAM_KEY);
    req.onsuccess=()=>resolve(String(req.result||''));
    req.onerror=()=>reject(req.error);
  });
}

async function setNotificationTeamId(teamId){
  const db=await openDb();
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(STORE,'readwrite');
    tx.objectStore(STORE).put(String(teamId||''),TEAM_KEY);
    tx.oncomplete=()=>resolve();
    tx.onerror=()=>reject(tx.error);
  });
}

async function setLastId(id){
  const db=await openDb();
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(STORE,'readwrite');
    tx.objectStore(STORE).put(Number(id||0),KEY);
    tx.oncomplete=()=>resolve();
    tx.onerror=()=>reject(tx.error);
  });
}

async function getFeedToken(){const db=await openDb();return new Promise((resolve,reject)=>{const req=db.transaction(STORE,'readonly').objectStore(STORE).get(FEED_KEY);req.onsuccess=()=>resolve(String(req.result||''));req.onerror=()=>reject(req.error);});}
async function setOwnerFeed(token,id){const db=await openDb();await new Promise((resolve,reject)=>{const tx=db.transaction(STORE,'readwrite');tx.objectStore(STORE).put(token,FEED_KEY);tx.objectStore(STORE).put(Number(id||0),KEY);tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);});}
self.addEventListener('install',event=>{self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(clients.claim());});
let delivery=Promise.resolve();
let feedVersion=0;
const serialize=task=>{const next=delivery.catch(()=>{}).then(task);delivery=next;return next;};
self.addEventListener('message',event=>{
  if(event.data?.type==='set-owner-feed'){
    feedVersion++;
    event.waitUntil(serialize(()=>setOwnerFeed(String(event.data.token||''),event.data.lastId)).then(()=>event.ports[0]?.postMessage({ok:true})));
  }
  if(event.data?.type==='set-last-notification-id')event.waitUntil(serialize(()=>setLastId(event.data.id||0)));
  if(event.data?.type==='set-notification-team')event.waitUntil(setNotificationTeamId(event.data.teamId||''));
});

self.addEventListener('push',event=>{
  event.waitUntil(serialize(async()=>{
    const version=feedVersion;
    const token=await getFeedToken();
    if(!token)return;
    let lastId=await getLastId();
    // Drain the feed in order. Overlapping wakeups share one cursor, so no alert repeats.
    for(let page=0;page<3;page++){
      const controller=new AbortController();
      const timeout=setTimeout(()=>controller.abort(),8000);
      let response;
      try{
        response=await fetch('/push/notifications?after='+encodeURIComponent(lastId),{
          credentials:'same-origin',headers:{Authorization:'Bearer '+token},cache:'no-store',signal:controller.signal
        });
      }finally{clearTimeout(timeout);}
      if(!response.ok||version!==feedVersion)return;
      const data=await response.json();
      const notifications=Array.isArray(data.notifications)?data.notifications:[];
      let advanced=false;
      for(const item of notifications){
        const id=Number(item.id);
        if(!Number.isInteger(id)||id<=lastId)continue;
        if(version!==feedVersion)return;
        await self.registration.showNotification(item.title||'ECFHL',{
          body:item.body||'',icon:'/ecfhl-logo.png',badge:'/favicon.svg',tag:'ecfhl-'+id,
          data:{url:item.url||'/teams/current'}
        });
        lastId=id;advanced=true;
        // Keep delivered alerts delivered even if a later notification fails.
        await setLastId(lastId);
      }
      if(notifications.length<100||!advanced)return;
    }
  }));
});

self.addEventListener('notificationclick',event=>{
  const url=event.notification.data?.url||'/';
  event.waitUntil((async()=>{
    const target=new URL(url,self.location.origin);
    if(target.origin!==self.location.origin)return clients.openWindow(target.href);
    const all=await clients.matchAll({type:'window',includeUncontrolled:true});
    for(const client of all){
      if('focus' in client){
        try{
          await client.navigate(target.href);
          return client.focus();
        }catch(e){}
      }
    }
    return clients.openWindow(target.href);
  })());
});
