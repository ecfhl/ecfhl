const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const node = () => ({textContent:'',attributes:{},setAttribute(key,value){this.attributes[key]=value;},removeAttribute(key){delete this.attributes[key];}});
const keys=['players','projections','odds'];
const cards=Object.fromEntries(keys.map(key=>{
  const elements=Object.fromEntries(['.collector-state','.collector-message','progress','.collector-units','.collector-details pre','.collector-updated'].map(selector=>[selector,node()]));
  const card={...node(),dataset:{jobKey:key},querySelector:selector=>elements[selector]};return [key,card];
}));
const forms=keys.map(key=>({dataset:{job:key},querySelector:()=>node(),addEventListener(event,handler){this.handler=handler;}}));
const buttons=forms.map(()=>({disabled:false}));
const ticker=node();const notification={addEventListener(){}};
const root={querySelector(selector){if(selector.includes('data-job-key'))return cards[selector.match(/"([^"]+)"/)[1]];if(selector==='.collector-ticker')return ticker;if(selector==='.test-notification-form')return notification;return {value:'csrf'};},querySelectorAll(selector){return selector==='.job-ajax-form'?forms:selector==='.job-ajax-form button'?buttons:Object.values(cards);}};
let responseStatus=500;
const scheduled=[];
const states={players:{status:'running',message:'Collecting…',completed:1,total:2,details:'<unsafe>'},projections:{status:'running',message:'Working',completed:0,total:null},odds:{status:'failed',message:'Failed',completed:0,total:2}};
const requests=[];
const fetch=async(url,options)=>{
  requests.push({url,options});
  if(url==='/job-status/state')return {ok:true,json:async()=>states};
  return {ok:responseStatus===200,json:async()=>responseStatus===200?{ok:true,details:{players:{status:'success'}}}:{message:'Server error'}};
};
vm.runInNewContext(fs.readFileSync('public/collector-status.js','utf8'),{document:{hidden:false,addEventListener(_,handler){handler();},querySelector(){return root;}},fetch,setTimeout(callback){scheduled.push(callback);}});
const settle=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 await settle();
 assert.equal(cards.players.dataset.status,'running');assert.equal(cards.players.querySelector('progress').value,1);assert.equal(cards.players.querySelector('progress').max,2);
 assert.equal(cards.projections.querySelector('progress').attributes.value,undefined,'Unknown totals must remain indeterminate');
 assert.equal(cards.players.querySelector('.collector-details pre').textContent,'<unsafe>','Diagnostics must use textContent');
 assert.equal(cards.odds.dataset.status,'failed');assert.equal(cards.odds.querySelector('progress').value,0);
 await forms[0].handler({preventDefault(){}});
 assert.equal(ticker.textContent,'Server error','A generic HTTP 500 must never appear successful');assert(buttons.every(button=>!button.disabled),'Failure must release buttons');
 assert.equal(requests.find(request=>request.url.includes('/run/')).options.headers['X-CSRF-TOKEN'],'csrf');
 responseStatus=200;states.players={status:'success',message:'Completed',completed:2,total:2};states.projections.status='idle';
 await forms[0].handler({preventDefault(){}});
 assert.equal(cards.players.dataset.status,'success');assert.equal(cards.players.querySelector('progress').value,2);assert(ticker.textContent.includes('1 checked'));
 assert.equal(scheduled.length,1,'Polling must be serialized instead of overlapping intervals');
 console.log('Collector UI checks passed: real progress, unknown totals, diagnostics escaping, HTTP failure, CSRF, success and serialized polling.');
})().catch(error=>{console.error(error);process.exit(1);});
