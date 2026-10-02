const DB_NAME='ecfhl-push';
const STORE='state';
const KEY='lastNotificationId';
const TEAM_KEY='notificationTeamId';

function openDb(){
  return new Promise((resolve,reject)=>{
    const req=indexedDB.open(DB_NAME,1);
    req.onupgradeneeded=()=>req.result.createObjectStore(STORE);
    req.onsuccess=()=>resolve(req.result);
    req.onerror=()=>reject(req.error);
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

self.addEventListener('message',event=>{
  if(event.data?.type==='set-last-notification-id'){
    event.waitUntil(setLastId(event.data.id||0));
  }
  if(event.data?.type==='set-notification-team'){
    event.waitUntil(setNotificationTeamId(event.data.teamId||''));
  }
});

self.addEventListener('push',event=>{
  event.waitUntil((async()=>{
    const lastId=await getLastId();
    const response=await fetch('/push/notifications?after='+encodeURIComponent(lastId),{
      credentials:'same-origin',
      cache:'no-store'
    });
    if(!response.ok)return;
    const data=await response.json();
    const notifications=Array.isArray(data.notifications)?data.notifications:[];
    let maxId=lastId;
    const notificationTeamId=await getNotificationTeamId();

    for(const item of notifications){
      maxId=Math.max(maxId,Number(item.id||0));
      if(item.category==='live-score' && (!notificationTeamId || String(item.fantasy_team_id||'')!==notificationTeamId))continue;
      await self.registration.showNotification(item.title||'ECFHL',{
        body:item.body||'',
        icon:'/ecfhl-logo.png',
        badge:'/favicon.svg',
        tag:'ecfhl-'+String(item.id||Date.now()),
        data:{url:item.url||'/teams/current'}
      });
    }

    if(maxId>lastId)await setLastId(maxId);
  })());
});

self.addEventListener('notificationclick',event=>{
  event.notification.close();
  const url=event.notification.data?.url||'/';
  event.waitUntil((async()=>{
    const all=await clients.matchAll({type:'window',includeUncontrolled:true});
    for(const client of all){
      if('focus' in client){
        try{
          await client.navigate(url);
          return client.focus();
        }catch(e){}
      }
    }
    return clients.openWindow(url);
  })());
});
