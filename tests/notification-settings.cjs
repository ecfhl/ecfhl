const fs=require('node:fs'),assert=require('node:assert/strict'),{chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'}),page=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const root=process.cwd();let saved=[],pending=[],fail=false;
 await page.route('https://ecfhl.test/**',async route=>{
  const url=new URL(route.request().url());
  if(url.pathname.endsWith('.js')||url.pathname.endsWith('.css'))return route.fulfill({path:root+'/public'+url.pathname});
  if(route.request().method()==='POST'&&url.pathname==='/notifications'){
   const preferences=route.request().postDataJSON();saved.push(preferences);
   if(fail)return route.fulfill({status:500,json:{message:'Please retry saving.'}});
   return new Promise(resolve=>pending.push(async()=>{await route.fulfill({json:{preferences}});resolve();}));
  }
  if(url.pathname.startsWith('/api/')||url.pathname.startsWith('/push/'))return route.fulfill({json:{preferences:{},owners:{},notifications:[],unread:{total:0},messages:[],latest_id:0}});
  return route.fulfill({contentType:'text/html; charset=utf-8',body:fs.readFileSync('storage/app/settings-test.html','utf8')});
 });
 await page.goto('https://ecfhl.test/notifications#alerts');
 const toggle=page.locator('#scoring-panel-enabled'),controls=page.locator('#scoring-settings-controls');
 await toggle.uncheck();assert.equal(await controls.isVisible(),false);assert.equal(saved.length,0,'Local scoring switch does not submit unrelated notifications');
 await page.reload();assert.equal(await toggle.isChecked(),false);await toggle.check();assert.equal(await controls.isVisible(),true);
 await page.locator('#live-score-updates-scope').selectOption('league');await page.reload();assert.equal(await page.locator('#live-score-updates-scope').inputValue(),'league');
 const popup=page.locator('[name="private_message_popups"][type="checkbox"]');await popup.uncheck();
 await page.waitForFunction(()=>document.querySelector('#owner-save-state').dataset.state==='saving');
 await page.locator('[name="league_message_popups"][type="checkbox"]').uncheck();assert.equal(saved.length,1,'Only one write in flight');
 await pending.shift()();await page.waitForTimeout(100);assert.equal(saved.length,2,'Later changes are queued');
 assert.equal(saved[1].private_message_popups,false);assert.equal(saved[1].league_message_popups,false);
 await pending.shift()();await page.waitForFunction(()=>document.querySelector('#owner-save-state').dataset.state==='saved');
 fail=true;await popup.check();await page.waitForFunction(()=>document.querySelector('#owner-save-state').dataset.state==='error');
 assert.equal(await page.locator('#owner-save-preferences').isVisible(),true);fail=false;await page.locator('#owner-save-preferences').click();await page.waitForTimeout(100);await pending.shift()();await page.waitForFunction(()=>document.querySelector('#owner-save-state').dataset.state==='saved');
 assert.deepEqual(errors,[]);await browser.close();console.log('Settings browser checks passed: immediate serialized saves, retry, scoring switch and dropdown persistence.');
})().catch(e=>{console.error(e);process.exit(1);});
