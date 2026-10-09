const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.resolve(__dirname,'..');
 let html=`<!doctype html><html data-theme="dark"><head><link rel="stylesheet" href="/themes.css"><link rel="stylesheet" href="/communication.css"><style>:root{--panel:#142033;--text:#fff;--line:#334155;--muted:#94a3b8}body{margin:0}.mobile-primary-nav{display:block;position:fixed;bottom:8px;height:70px;left:8px;right:8px}@media(min-width:851px){.mobile-primary-nav{display:none}}</style></head><body><div id="communication-context" data-user-id="0"></div><button id="header-notifications-toggle" aria-controls="notification-panel">Notifications</button><span id="header-notification-status"></span><button id="live-score-updates-toggle" aria-controls="live-score-updates">Scores</button><span id="live-score-updates-count"></span><section id="notification-panel"></section><section id="live-score-updates"></section><section id="chat-panel" aria-label="Chat" hidden></section><nav class="mobile-primary-nav"></nav><script src="/live-score-updates.js"></script><script src="/app-communication.js"></script><script src="/popup-resize.js"></script></body></html>`;
 for(const [id,file] of [['live-score-updates','scoring-popup'],['notification-panel','notification-panel']]){
  let markup=fs.readFileSync(root+'/resources/views/communication/'+file+'.blade.php','utf8');
  markup=markup.replace(/@auth([\s\S]*?)@else[\s\S]*?@endauth/g,'$1').replace(/{{[\s\S]*?}}/g,'1');
  html=html.replace(new RegExp('<section id="'+id+'"[\\s\\S]*?</section>'),markup);
 }
 html=html.replace('</body>','<script src="/update-sheets.js"></script></body>');
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://ecfhl.test/**',async route=>{
  const url=new URL(route.request().url());
  if(url.pathname==='/account')return route.fulfill({contentType:'text/html',body:html});
  if(url.pathname==='/api/scoring-updates')return route.fulfill({json:{date:'2026-10-08',players:[],teams:[],matchups:[]}});
  if(url.pathname.startsWith('/api/'))return route.fulfill({json:{unread:{total:0},latest_id:0,notifications:[],preferences:{},owners:{},messages:[]}});
  const file=path.join(root,'public',url.pathname);if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
  return route.fulfill({status:404,body:''});
 });
 await page.goto('https://ecfhl.test/account');
 await page.waitForFunction(()=>window.ecfhlScoringPopup);
 await page.evaluate(()=>{
  const ui=window.ecfhlScoringPopup,scope=document.getElementById('live-score-updates-scope');scope.value='nhl';scope.dispatchEvent(new Event('change'));
  ui.update({date:'2026-10-08',players:[],nhlEvents:[]});
  ui.update({date:'2026-10-08',players:[],nhlEvents:[{key:'goal1',team:'OTT 2 @ BOS 3',game:{team:'OTT',score:2,opponent:'BOS',opponentScore:3,home:false,scoringTeam:'OTT'},player:'N. Cousins — Test team',stats:'Unassisted goal',points:0}]});
 });
 await page.click('#live-score-updates-toggle');
 const score=page.locator('#live-score-updates');
 assert.equal(await score.locator('.panel-resize-handle').count(),0);
 assert.equal(await score.locator('#live-score-updates-minimize').count(),0);
 assert.equal(await score.locator('.scoring-team-highlight').innerText(),'OTT 2');
 assert.match(await score.locator('.live-score-update-team-heading').innerText(),/OTT 2\s*@\s*BOS 3/);
 const box=await score.boundingBox();assert(box.x<=12 && box.width>=366 && box.y>200 && box.y+box.height<=844);
 await page.click('#header-notifications-toggle');assert.equal(await score.isVisible(),false);
 assert.equal(await page.locator('#notification-panel').isVisible(),true);
 assert.equal(await page.locator('#notification-panel .panel-resize-handle').count(),0);
 assert.equal(await page.locator('#chat-panel .panel-resize-handle').count(),1);
 await page.setViewportSize({width:1280,height:900});
 const desktop=await page.locator('#notification-panel').boundingBox();assert(desktop.width<=420 && desktop.x>800 && desktop.y<120);
 assert.deepEqual(errors,[]);
 await browser.close();console.log('Update sheets browser checks passed: mobile/desktop layout, scoring-team highlight, exclusive panels, simplified controls and chat resizing.');
})().catch(e=>{console.error(e);process.exit(1)});
