const assert=require('node:assert/strict');
const fs=require('node:fs');const vm=require('node:vm');
let click,request,fail=false;const rows=[];
const message={hidden:true,replaceChildren(){},append(){},setAttribute(){}};
const make=(id='g1')=>{const attrs={'aria-pressed':'false'};const button={dataset:{goalieWatch:'MTL|persistentgoalie',goalieName:'Goalie, Persistent',goaliePlayerId:id},disabled:false,getAttribute:k=>attrs[k],setAttribute:(k,v)=>attrs[k]=v,closest(){return button}};return button};
vm.runInNewContext(fs.readFileSync('public/goalie-watches.js','utf8'),{
 document:{querySelectorAll:()=>rows,querySelector:()=>({content:'csrf'}),createElement:()=>message,createTextNode:v=>v,body:{append(){}},addEventListener:(type,fn)=>{if(type==='click')click=fn}},
 fetch:async(url,options)=>{request={url,...options};return {status:fail?500:200,ok:!fail,json:async()=>fail?{message:'Failed'}:{enabled:JSON.parse(options.body).enabled}}},
 location:{assign(){throw Error('Unexpected login')}},setTimeout:()=>1,clearTimeout(){}
});
(async()=>{
 const a=make(),b=make();rows.push(a,b); // Rows added after script initialization.
 await click({target:a,preventDefault(){}});
 assert.equal(request.url,'/notifications/goalie');assert.deepEqual(JSON.parse(request.body),{key:'MTL|persistentgoalie',enabled:true,player_id:'g1'});
 assert.equal(a.getAttribute('aria-pressed'),'true');assert.equal(b.getAttribute('aria-pressed'),'true');assert.equal(a.disabled,false);
 await click({target:b,preventDefault(){}});assert.equal(a.getAttribute('aria-pressed'),'false');assert.equal(b.getAttribute('aria-pressed'),'false');
 fail=true;await click({target:a,preventDefault(){}});assert.equal(a.getAttribute('aria-pressed'),'false');assert.equal(a.disabled,false);
 console.log('Goalie bell tests passed: appended rows, persistent player ID, duplicate state, unsubscribe, failed-save recovery.');
})().catch(error=>{console.error(error);process.exitCode=1});
