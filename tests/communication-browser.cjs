const { chromium } = require('playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.resolve(__dirname,'..');const html=fs.readFileSync(root+'/storage/app/communication-test.html','utf8');
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH || (fs.existsSync('/usr/bin/chromium') ? '/usr/bin/chromium' : undefined),headless:true,args:['--no-sandbox']});
 const ctx=await browser.newContext({viewport:{width:1280,height:900}});const page=await ctx.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const prefs={notifications_enabled:true,private_message_popups:true,private_message_push:true,league_message_popups:true,league_message_push:true,team_scores:true};
 let score=0,latest=1,notificationRead=false,unread=0;
 const messages=[{id:1,sender_id:2,recipient_id:1,body:'Hello <script>window.injected=true</script>',created_at:'2026-10-08 16:00:00',sender_name:'Bob',team_name:'Bob Team'}];
 const scoring=()=>({date:'2026-10-08',players:[{key:'1|player',teamId:'1',team:'Alice Team',name:'Player',nhl:'WSH',goalie:false,change:'up',points:score*2,stats:{G:score}}],teams:[{id:'1',name:'Alice Team'},{id:'2',name:'Bob Team'}],matchups:[{away_team_id:'1',home_team_id:'2'}]});
 await page.route('https://ecfhl.test/**',async route=>{
  const request=route.request(),url=new URL(request.url()),body=request.method()==='POST'?request.postDataJSON():{};
  let json;
  if(url.pathname==='/api/scoring-updates')json=scoring();
  else if(url.pathname==='/api/messages/state')json={unread:{total:unread,private:unread,league:0},latest_id:latest,messages:url.searchParams.has('after')?messages.filter(m=>m.sender_id!==1&&m.id>Number(url.searchParams.get('after'))):[],notification_count:notificationRead?0:1,notifications:[{id:9,title:'Goal',body:'Scored',url:'/teams/current',read_at:notificationRead?'now':null}],owners:{'bob-team':2},preferences:prefs};
  else if(url.pathname==='/api/messages/conversation')json={messages:messages.filter(m=>m.sender_id===2||m.sender_id===1).filter(m=>!url.searchParams.has('after')||m.id>Number(url.searchParams.get('after'))),has_more:false};
  else if(url.pathname==='/api/messages/read'){unread=0;json={unread:{total:0,private:0,league:0}};}
  else if(url.pathname==='/api/notifications/read'){notificationRead=true;json={ok:true};}
  else if(url.pathname==='/api/communication/preferences'){Object.assign(prefs,body);json={preferences:prefs};}
  else if(url.pathname==='/api/messages/send'){latest++;const m={id:latest,sender_id:1,recipient_id:2,body:body.body,created_at:'2026-10-08 16:01:00',sender_name:'Alice',team_name:'Alice Team'};messages.push(m);json={message:m};}
  if(json)return route.fulfill({json});
  const local=path.join(root,'public',decodeURIComponent(url.pathname));
  if(fs.existsSync(local)&&fs.statSync(local).isFile())return route.fulfill({path:local});
  if(url.pathname==='/messages'||url.pathname==='/account')return route.fulfill({contentType:'text/html',body:html});
  return route.fulfill({status:404,body:''});
 });
 await page.goto('https://ecfhl.test/messages?user_id=2');await page.waitForSelector('.chat-message');
 assert.equal(await page.evaluate(()=>window.injected),undefined,'Messages execute HTML');
 await page.locator('#live-score-updates-toggle').click();assert.equal(await page.locator('#live-score-updates').isVisible(),true);
 await page.locator('#live-score-updates-close').click();await page.locator('#live-score-updates-toggle').click();assert.equal(await page.locator('#live-score-updates-enabled').isChecked(),true,'Single click turns receiving off');
 await page.locator('#live-score-updates-close').click();score++;
 await page.evaluate(()=>window.dispatchEvent(new Event('ecfhl-scoring-filter')));await page.waitForFunction(()=>document.getElementById('live-score-updates-count').textContent==='1');assert.equal(await page.locator('#live-score-updates-restore').isVisible(),true);
 await page.goto('https://ecfhl.test/account');await page.waitForFunction(()=>document.getElementById('live-score-updates-count').textContent==='1');await page.locator('#live-score-updates-restore').click();assert.equal(await page.locator('#live-score-updates').getAttribute('data-minimized'),'false');
 await page.locator('#live-score-updates-enabled').uncheck();await page.locator('#live-score-updates-close').click();score++;await page.evaluate(()=>window.dispatchEvent(new Event('ecfhl-scoring-filter')));await page.waitForTimeout(150);assert.equal(await page.locator('#live-score-updates-count').textContent(),'1');
 await page.locator('#live-score-updates-toggle').click();assert.equal(await page.locator('#live-score-updates-enabled').isChecked(),false);await page.locator('#live-score-updates-enabled').check();await page.locator('#live-score-updates-close').click();
 await page.locator('#header-notifications-toggle').click();await page.waitForSelector('.notification-entry');await page.locator('#header-notifications-enabled').uncheck();await page.waitForFunction(()=>document.getElementById('header-notification-status').dataset.enabled==='false');await page.locator('#notification-mark-read').click();await page.waitForFunction(()=>document.getElementById('header-notification-count').textContent==='0');await page.locator('[data-close-notifications]').click();
 await page.locator('.chat-form textarea').fill('Test message <img src=x onerror="window.injected=true">');await page.locator('.chat-form button').click();await page.waitForFunction(()=>document.querySelector('.chat-status').textContent==='Sent.');assert.equal(await page.evaluate(()=>window.injected),undefined);assert.equal(await page.locator('.chat-message').count(),2);
 await page.evaluate(()=>{const m=document.getElementById('team-icon-modal');m.dataset.messageSlug='bob-team';window.dispatchEvent(new Event('ecfhl-team-viewer'));});assert.equal(await page.locator('#team-icon-modal-message').getAttribute('href'),'/messages?user_id=2');
 await page.evaluate(()=>{const m=document.getElementById('team-icon-modal');m.dataset.messageSlug='alice-team';window.dispatchEvent(new Event('ecfhl-team-viewer'));});assert.equal(await page.locator('#team-icon-modal-message').isVisible(),false);
 // A private message received on a page without an open conversation gets a popup.
 await page.evaluate(()=>document.querySelector('.chat-log').style.display='none');latest++;messages.push({id:latest,sender_id:3,recipient_id:1,body:'Incoming message',created_at:'2026-10-08 16:02:00',sender_name:'Carol',team_name:'Carol Team'});unread=1;
 await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForSelector('.message-popup');assert.equal(await page.locator('#header-message-count').textContent(),'1');await page.locator('.message-popup-close').click();assert.equal(await page.locator('.message-popup').count(),0);
 await page.setViewportSize({width:360,height:780});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=360),true,'Mobile header overflows');
 await page.locator('#live-score-updates-toggle').click();if(process.env.ECFHL_BROWSER_SCREENSHOT)await page.screenshot({path:process.env.ECFHL_BROWSER_SCREENSHOT,fullPage:false});
 assert.deepEqual(errors,[],'Browser runtime errors');await browser.close();console.log('Browser checks passed: shared header, one-click reopening, navigation persistence, On/Off, chat send/XSS, bell read/status, owner link, incoming popup/count and mobile layout.');
})().catch(e=>{console.error(e);process.exit(1)});
