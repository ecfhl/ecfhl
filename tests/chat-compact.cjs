const {chromium}=require('playwright'),fs=require('fs'),path=require('path'),assert=require('assert/strict');
(async()=>{
 const root=path.resolve(__dirname,'..'),html=fs.readFileSync(root+'/storage/app/communication-test.html','utf8');
 const browser=await chromium.launch({headless:true}),page=await browser.newPage({viewport:{width:1100,height:850}}),errors=[],sends=[];page.on('pageerror',e=>errors.push(e.message));
 const message={id:1,sender_id:2,recipient_id:null,body:'**Welcome** to the league!',team_name:'Orcas',created_at:'2026-10-09T12:00:00Z',likes:1,liked:false,viewers:[]};
 await page.route('https://ecfhl.test/**',route=>{
  const req=route.request(),url=new URL(req.url()),body=req.method()==='POST'?req.postDataJSON():{};let json;
  if(url.pathname==='/api/messages/state')json={teams:[{user_id:2,team_name:'Orcas'}],received_conversations:[{user_id:9,team_name:'Gary Bettman',read_only:true}],owners:{},preferences:{},notifications:[],notification_count:0,unread:{total:1,league:0,people:{9:1}},latest_id:2,messages:[]};
  else if(url.pathname==='/api/messages/conversation')json={messages:url.searchParams.get('user_id')==='9'?[{...message,id:2,sender_id:9,recipient_id:1,body:'Gary announcement',team_name:'Gary Bettman'}]:url.searchParams.has('after')?[]:[message],receipts:[],has_more:false};
  else if(url.pathname.endsWith('/reaction')){message.liked=body.active;message.likes=body.active?2:1;json={message};}
  else if(url.pathname==='/api/messages/send'){sends.push(body);json={message:{...message,id:10+sends.length,sender_id:1,body:body.body}};}
  else if(url.pathname==='/api/messages/read')json={unread:{total:0}};
  else if(url.pathname==='/api/scoring-updates')json={date:'2026-10-09',players:[],teams:[],matchups:[]};else if(url.pathname.startsWith('/api/'))json={};
  if(json)return route.fulfill({json});const file=path.join(root,'public',url.pathname);if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});return route.fulfill({contentType:'text/html',body:html});
 });
 await page.goto('https://ecfhl.test/account');await page.waitForFunction(()=>document.querySelector('#chat-panel-conversation').options.length===3);
 await page.locator('.header-actions a[href="/messages"]').click();await page.getByText('Gary announcement',{exact:true}).waitFor();assert.equal(await page.locator('#chat-panel-conversation').inputValue(),'9');assert(!(await page.locator('#chat-panel-form').isVisible()));
 await page.locator('#chat-panel-conversation').selectOption('');await page.getByText('Welcome',{exact:true}).waitFor();assert(await page.locator('.chat-date-divider').count());
 await page.locator('.chat-reaction').first().click();await page.waitForFunction(()=>document.querySelector('.chat-reaction').getAttribute('aria-pressed')==='true');assert.equal(await page.locator('.chat-reaction').first().getAttribute('aria-pressed'),'true');await page.locator('.chat-reaction').first().click();await page.waitForFunction(()=>document.querySelector('.chat-reaction').getAttribute('aria-pressed')==='false');assert.equal(await page.locator('.chat-reaction').first().getAttribute('aria-pressed'),'false');
 const text=page.locator('#chat-panel-text');await text.fill('Hi');await text.press('ControlOrMeta+A');await page.getByRole('button',{name:'Bold',exact:true}).click();assert.equal(await text.inputValue(),'**Hi**');
 await page.getByRole('button',{name:'Insert emoji',exact:true}).click();await page.getByRole('button',{name:'Insert 🔥',exact:true}).click();assert((await text.inputValue()).includes('🔥'));await page.getByRole('button',{name:'Send message',exact:true}).click();await page.waitForFunction(()=>document.querySelector('#chat-panel-text').value==='');assert.equal(sends.length,1);
 await page.locator('#chat-panel-file').setInputFiles({name:'tiny.gif',mimeType:'image/gif',buffer:Buffer.from('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==','base64')});await page.locator('#chat-panel-attachment').waitFor({state:'visible'});await page.getByRole('button',{name:'Send message',exact:true}).click();await page.waitForFunction(()=>document.querySelector('#chat-panel-attachment').hidden);assert(sends[1].attachment.startsWith('data:image/gif;base64,'));
 await page.screenshot({path:'storage/app/compact-chat-preview.png'});await page.setViewportSize({width:390,height:800});assert((await page.locator('#chat-panel').boundingBox()).width<=390);assert.equal(errors.length,0,errors.join('\n'));
 console.log('Compact chat passed: Gary default/read-only, dates, formatting, emoji, sending, GIF uploads, reactions and mobile width.');await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
