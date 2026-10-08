const {chromium}=require('playwright'),fs=require('fs'),path=require('path'),assert=require('assert/strict');
(async()=>{
 const root=path.resolve(__dirname,'..'),html=fs.readFileSync(root+'/storage/app/communication-test.html','utf8');
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{channel:'chrome'})});
 const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://ecfhl.test/**',async route=>{
  const url=new URL(route.request().url());let json;
  if(url.pathname==='/api/messages/state')json={teams:[{user_id:2,team_name:'Bob Team'}],owners:{},preferences:{},notifications:[],notification_count:0,unread:{total:0},latest_id:0,messages:[]};
  else if(url.pathname==='/api/messages/conversation')json={messages:[],has_more:false};
  else if(url.pathname==='/api/scoring-updates')json={date:'2026-10-08',players:[],teams:[],matchups:[]};
  else if(url.pathname.startsWith('/api/'))json={};
  if(json)return route.fulfill({json});
  const local=path.join(root,'public',decodeURIComponent(url.pathname));if(fs.existsSync(local)&&fs.statSync(local).isFile())return route.fulfill({path:local});
  if(url.pathname==='/account')return route.fulfill({contentType:'text/html; charset=utf-8',body:html});return route.fulfill({status:404,body:''});
 });
 const panels=[
 {id:'live-score-updates',open:'#live-score-updates-toggle',handle:'#live-score-updates-handle',close:'#live-score-updates-close',minimize:'#live-score-updates-minimize',restore:'#live-score-updates-restore'},
 {id:'notification-panel',open:'#header-notifications-toggle',handle:'#notification-handle',close:'[data-close-notifications]',minimize:'#notification-minimize',restore:'#notification-restore'},
 {id:'chat-panel',open:'.header-actions a[href="/messages"]',handle:'#chat-panel-handle',close:'#chat-panel-close',minimize:'#chat-panel-minimize',restore:'#chat-panel-restore'}
 ];
 await page.goto('https://ecfhl.test/account');await page.waitForFunction(()=>document.querySelectorAll('.panel-resize-handle').length===3);
 const dimensions={};
 for(const p of panels){
  await page.locator(p.open).click();const panel=page.locator('#'+p.id);await panel.waitFor({state:'visible'});
  const heading=await page.locator(p.handle).boundingBox();await page.mouse.move(heading.x+45,heading.y+15);await page.mouse.down();await page.mouse.move(145,115);await page.mouse.up();
  const order=await panel.locator('.live-score-updates-actions,.notification-panel-actions').evaluate(el=>[...el.children].map(child=>child.id.endsWith('-trash')?'trash':child.tagName==='A'?'settings':child.id.endsWith('-maximize')?'maximize':child.id.endsWith('-minimize')?'minimize':child.classList.contains('panel-header-restore')?'restore':'close'));assert.deepEqual(order,p.id==='chat-panel'?['settings','minimize','restore','maximize','close']:['trash','settings','minimize','restore','close']);
  const before=await panel.boundingBox(),grip=await panel.locator('.panel-resize-handle').boundingBox();await page.mouse.move(grip.x+10,grip.y+10);await page.mouse.down();await page.mouse.move(grip.x+70,grip.y+90);await page.mouse.up();
  const after=await panel.boundingBox();assert(after.width>before.width+40&&after.height>before.height+40,p.id+' resizes on both axes');
  await panel.locator('.panel-resize-handle').focus();await page.keyboard.press('ArrowRight');const keyboard=await panel.boundingBox();assert(keyboard.width>after.width,p.id+' supports keyboard resizing');
  dimensions[p.id]=keyboard;await page.locator(p.minimize).click();await page.waitForTimeout(50);assert(await page.locator(p.handle).isVisible(),p.id+' retains its orange header when minimized');assert(await page.locator(p.restore).isVisible());
  const small=await panel.boundingBox();assert(small.height<keyboard.height,p.id+' collapses despite its saved expanded height');
  await page.locator(p.restore).click();await page.waitForTimeout(50);const restored=await panel.boundingBox();assert(Math.abs(restored.height-keyboard.height)<2,p.id+' restores expanded height');
  const shrinkGrip=await panel.locator('.panel-resize-handle').boundingBox();await page.mouse.move(shrinkGrip.x+10,shrinkGrip.y+10);await page.mouse.down();await page.mouse.move(shrinkGrip.x+10,50);await page.mouse.up();await page.waitForTimeout(50);
  assert.equal(await panel.getAttribute('data-minimized'),'true',p.id+' can shrink to minimized height');
  await panel.locator('.panel-header-restore').click();await page.waitForTimeout(50);assert.equal(await panel.getAttribute('data-minimized'),'false',p.id+' restores from header');
  await page.locator(p.close).click();
 }
 await page.locator('#live-score-updates-toggle').click();await page.locator('#live-score-updates-minimize').click();
 assert.equal(await page.locator('#live-score-updates-handle h2').textContent(),'Scoring Updates');
 const settings=page.locator('.scoring-settings-icon');assert.equal(await settings.getAttribute('href'),'/notifications#alerts');assert(await settings.isVisible());assert.equal(await page.locator('.scoring-settings-link').count(),0);
 await page.reload();await page.waitForSelector('#live-score-updates-handle');assert(await page.locator('#live-score-updates-handle').isVisible());
 await page.locator('#live-score-updates-restore').click();await page.waitForTimeout(50);const saved=await page.locator('#live-score-updates').boundingBox();assert(Math.abs(saved.width-dimensions['live-score-updates'].width)<2&&Math.abs(saved.height-dimensions['live-score-updates'].height)<2,'Size survives navigation');
 await page.locator('#live-score-updates-close').click();await page.setViewportSize({width:360,height:780});
 for(const p of panels){await page.locator(p.open).click();await page.waitForTimeout(50);const rect=await page.locator('#'+p.id).boundingBox(),nav=await page.locator('.mobile-primary-nav').boundingBox();assert(rect.x>=8&&rect.x+rect.width<=352,p.id+' stays within mobile width');assert(rect.y>=8&&rect.y+rect.height<=nav.y-7,p.id+' stays above mobile navigation');await page.locator(p.close).click();}
 assert.deepEqual(errors,[]);await browser.close();console.log('Popup resize checks passed: all three panels resize, keyboard controls, minimize/restore, saved sizes, scoring settings icon and mobile bounds.');
})().catch(e=>{console.error(e);process.exit(1)});

