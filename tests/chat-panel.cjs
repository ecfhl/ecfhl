const {chromium}=require('playwright'),fs=require('fs'),path=require('path'),assert=require('assert/strict');
(async()=>{
 const root=path.resolve(__dirname,'..'),html=fs.readFileSync(root+'/storage/app/communication-test.html','utf8');
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{channel:'chrome'})});
 const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[],reads=[],sends=[];
 page.on('pageerror',e=>errors.push(e.message));
 const prefs={notifications_enabled:true,private_message_popups:true,league_message_popups:true,private_message_push:true,league_message_push:true};
 const teams=[{user_id:2,team_name:'Bob Team'},{user_id:3,team_name:'Carol Team'}];
 const message=(id,sender_id,recipient_id,body)=>({id,sender_id,recipient_id,body,created_at:'2026-10-08T17:00:00-03:00',team_name:sender_id===3?'Carol Team':sender_id===1?'Alice Team':'Bob Team'});
 const chats={'':[message(1,2,null,'League only')],'2':[message(2,2,1,'Bob private')],'3':[message(3,3,1,'Carol private')]};
 let delayBob=false,releaseBob,delaySend=false,releaseSend,latest=3,incoming=[];
 await page.route('https://ecfhl.test/**',async route=>{
  const req=route.request(),url=new URL(req.url()),body=req.method()==='POST'?req.postDataJSON():{};let json;
  if(url.pathname==='/api/messages/state')json={teams,owners:{'bob-team':2,'carol-team':3},preferences:prefs,notifications:[],notification_count:0,unread:{total:1},latest_id:latest,messages:url.searchParams.has('after')?incoming.filter(m=>m.id>Number(url.searchParams.get('after'))):[]};
  else if(url.pathname==='/api/messages/conversation'){
   const value=url.searchParams.get('user_id')||'';if(value==='2'&&delayBob){delayBob=false;await new Promise(resolve=>releaseBob=resolve);}
   json={messages:chats[value].filter(m=>!url.searchParams.has('after')||m.id>Number(url.searchParams.get('after'))),has_more:false};
  }else if(url.pathname==='/api/messages/read'){reads.push(body);json={unread:{total:0}};}
  else if(url.pathname==='/api/messages/send'){sends.push(body);if(delaySend){delaySend=false;await new Promise(resolve=>releaseSend=resolve);}const m=message(++latest,1,body.user_id,body.body);chats[body.user_id||''].push(m);json={message:m};}
  else if(url.pathname==='/api/scoring-updates')json={date:'2026-10-08',players:[],teams:[],matchups:[]};
  else if(url.pathname.startsWith('/api/'))json={};
  if(json)return route.fulfill({json});
  const local=path.join(root,'public',decodeURIComponent(url.pathname));if(fs.existsSync(local)&&fs.statSync(local).isFile())return route.fulfill({path:local});
  if(url.pathname==='/account'||url.pathname==='/messages')return route.fulfill({contentType:'text/html; charset=utf-8',body:html});
  return route.fulfill({status:404,body:''});
 });
 await page.goto('https://ecfhl.test/account');await page.waitForFunction(()=>document.querySelectorAll('#chat-panel-conversation option').length===3);
 const badge=await page.locator('#header-message-count').evaluate(el=>({color:getComputedStyle(el).backgroundColor,position:getComputedStyle(el).position,text:el.textContent}));assert.deepEqual(badge,{color:'rgb(233, 35, 35)',position:'absolute',text:'1'},'Unread messages use a red overlay badge');
 assert(!(await page.locator('#header-notification-count').isVisible()));assert(!(await page.locator('#live-score-updates-count').isVisible()));
 assert.equal(await page.locator('.header-actions a[href="/messages"] > span').first().textContent(),'💬');
 assert.equal(await page.locator('.site-credit').textContent(),'Powered by Ꮮ૦ท૯⚡𐌕รคг Solutions');
 assert(!(await page.locator('#chat-panel').isVisible()));assert.equal(reads.length,0,'Closed panel does not mark messages read');
 await page.locator('.header-actions a[href="/messages"]').click();await page.waitForFunction(()=>document.getElementById('chat-panel-log').textContent.includes('League only'));
 await page.waitForFunction(()=>document.getElementById('header-message-count').hidden);
 assert.equal(await page.locator('#chat-panel-log .chat-message-identity').first().evaluate(el=>getComputedStyle(el).justifyContent),'flex-end');
 assert.equal(await page.locator('#chat-panel-log time').first().evaluate(el=>getComputedStyle(el).textAlign),'right');
 const layout=await page.locator('#chat-panel-log .chat-message').first().evaluate(row=>{const body=row.querySelector('p').getBoundingClientRect(),identity=row.querySelector('.chat-message-identity').getBoundingClientRect(),time=row.querySelector('time').getBoundingClientRect(),meta=row.querySelector('.chat-message-meta').getBoundingClientRect();return {ratio:body.width/(body.width+meta.width),top:body.top<=identity.top+1,left:body.left<identity.left,dateBelow:time.top>=identity.bottom};});
 assert(layout.ratio>=.599&&layout.top&&layout.left&&layout.dateBelow,'Message uses at least 60% width from top-left with date under team identity');
 assert.equal(page.url(),'https://ecfhl.test/account','Chat opens without navigation');
 await page.locator('#chat-panel-text').fill('League draft');delayBob=true;
 await page.locator('#chat-panel-conversation').selectOption('2');await page.waitForTimeout(100);await page.locator('#chat-panel-conversation').selectOption('3');
 await page.waitForFunction(()=>document.getElementById('chat-panel-log').textContent.includes('Carol private'));releaseBob();await page.waitForTimeout(150);
 assert(!(await page.locator('#chat-panel-log').textContent()).includes('Bob private'),'Late response stays in its conversation');
 await page.locator('#chat-panel-text').fill('Carol draft');await page.locator('#chat-panel-conversation').selectOption('2');
 await page.waitForFunction(()=>document.getElementById('chat-panel-log').textContent.includes('Bob private'));
 delaySend=true;await page.locator('#chat-panel-text').fill('Private to Bob <script>window.injected=true</script>');await page.locator('#chat-panel-form button').click();
 await page.waitForFunction(()=>document.getElementById('chat-panel-status').textContent==='Sending…');await page.locator('#chat-panel-conversation').selectOption('3');releaseSend();await page.waitForTimeout(150);
 assert.equal(sends[0].user_id,2,'Send retains its original recipient');assert.equal(await page.locator('#chat-panel-text').inputValue(),'Carol draft');
 assert(!(await page.locator('#chat-panel-log').textContent()).includes('Private to Bob'));assert.equal(await page.evaluate(()=>window.injected),undefined);
 await page.locator('#chat-panel-conversation').selectOption('');assert.equal(await page.locator('#chat-panel-text').inputValue(),'League draft');
 const handle=page.locator('#chat-panel-handle'),box=await handle.boundingBox();await page.mouse.move(box.x+45,box.y+15);await page.mouse.down();await page.mouse.move(400,330);await page.mouse.up();const position=await page.locator('#chat-panel').boundingBox();assert(position.x>100&&position.y>100);
 await page.locator('#chat-panel-minimize').click();assert(await page.locator('#chat-panel-restore').isVisible());const readCount=reads.length;
 chats[''].push(message(++latest,2,null,'New while minimized'));await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForTimeout(100);assert.equal(reads.length,readCount,'Minimized chat does not read new messages');
 await page.locator('#chat-panel-restore').click();await page.waitForFunction(()=>document.getElementById('chat-panel-log').textContent.includes('New while minimized'));
 await page.reload();await page.waitForSelector('#chat-panel');const restored=await page.locator('#chat-panel').boundingBox();assert(Math.abs(restored.x-position.x)<2,'Position persists across pages');
 await page.setViewportSize({width:360,height:780});assert.equal(await page.locator('.site-credit').evaluate(el=>getComputedStyle(el).whiteSpace),'nowrap');assert(await page.locator('.site-credit').evaluate(el=>el.scrollWidth<=el.clientWidth),'Footer fits on one line on mobile');const mobile=await page.locator('#chat-panel').boundingBox();assert(mobile.x>=8&&mobile.x+mobile.width<=352&&mobile.y>=8,'Chat stays within mobile viewport');
 await handle.focus();await page.keyboard.press('ArrowLeft');await page.keyboard.press('Escape');assert(!(await page.locator('#chat-panel').isVisible()));
 const popupMessage=message(++latest,3,1,'Incoming private popup');incoming.push(popupMessage);chats['3'].push(popupMessage);
 await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForSelector('.message-popup');await page.locator('.message-popup a').click();
 await page.waitForFunction(()=>document.getElementById('chat-panel-conversation').value==='3'&&document.getElementById('chat-panel-log').textContent.includes('Incoming private popup'));
 assert.equal(page.url(),'https://ecfhl.test/account','Popup opens the shared chat panel');
 await page.goto('https://ecfhl.test/messages?user_id=2');await page.waitForFunction(()=>document.getElementById('chat-panel-conversation').value==='2'&&document.getElementById('chat-panel-log').textContent.includes('Bob private'));
 const longName=await page.locator('#chat-panel-log .chat-message-identity strong').first().evaluate(el=>{el.textContent='A very long fantasy hockey team name that must stay on one line';const style=getComputedStyle(el);return {nowrap:style.whiteSpace,overflow:style.overflow,ellipsis:style.textOverflow,truncated:el.scrollWidth>el.clientWidth};});
 assert.deepEqual(longName,{nowrap:'nowrap',overflow:'hidden',ellipsis:'ellipsis',truncated:true});
 assert.deepEqual(errors,[]);if(process.env.ECFHL_CHAT_SCREENSHOT)await page.screenshot({path:process.env.ECFHL_CHAT_SCREENSHOT});
 await browser.close();console.log('Chat panel checks passed: team switching, private isolation, late responses, send recipient/drafts, escaping, drag, minimize/read behavior, persistence, mobile and direct links.');
})().catch(e=>{console.error(e);process.exit(1)});
