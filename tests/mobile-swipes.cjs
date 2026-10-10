const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const source=fs.readFileSync('public/navigation-feedback.js','utf8');
function setup(path='/standings'){
 const handlers={},clicks=[],links=['/','/teams/current','/standings','/teams/current/lone-tsar','/players'].map(href=>({href,click:()=>clicks.push(href)}));
 const nav={style:{display:'grid'},querySelectorAll:()=>links},body={classList:{contains:()=>false}};
 const target={closest:()=>null,scrollWidth:200,clientWidth:200,parentElement:body,style:{overflowX:'visible'}};
 const document={body,querySelector:q=>q==='.mobile-primary-nav'?nav:null,addEventListener:(name,fn)=>handlers[name]=fn};
 vm.runInNewContext(source.slice(source.indexOf('  const primaryNav ='),source.indexOf('  if (!invitation) return;')),{document,location:{href:'https://ecfhl.test'+path,pathname:path},getComputedStyle:e=>e.style,window:{innerWidth:390},URL,Date});
 const touch=(x,y=200)=>({clientX:x,clientY:y});
 function gesture(a,b,options={}){const t=options.target||target;handlers.touchstart({target:t,touches:[touch(a)]});if(options.move)handlers.touchmove({touches:[touch(b,options.move)]});if(options.cancel)handlers.touchcancel();handlers.touchend({target:t,touches:[],changedTouches:[touch(b,options.endY||200)]});}
 return {gesture,clicks,target,nav,handlers,touch};
}
let s;
const order=['/','/teams/current','/standings','/teams/current/lone-tsar','/players'];
for(let i=0;i<order.length;i++){
 s=setup(order[i]);s.gesture(280,100);assert.equal(s.clicks[0],order[(i+1)%order.length]);
 s=setup(order[i]);s.gesture(100,280);assert.equal(s.clicks[0],order[(i+order.length-1)%order.length]);
}
for(const options of [{move:270},{endY:270},{cancel:true}]){s=setup();s.gesture(280,100,options);assert.equal(s.clicks.length,0);}
s=setup();s.gesture(190,150);s.gesture(10,280);assert.equal(s.clicks.length,0);
s=setup();s.target.closest=()=>({});s.gesture(280,100);assert.equal(s.clicks.length,0);
s=setup();s.target.scrollWidth=600;s.target.style.overflowX='auto';s.gesture(280,100);assert.equal(s.clicks.length,0);
s=setup();s.nav.style.display='none';s.gesture(280,100);assert.equal(s.clicks.length,0);
s=setup('/rules');s.gesture(280,100);assert.equal(s.clicks.length,0);
console.log('Mobile swipes passed: ordering, team link, boundaries, vertical scroll, controls, scrollable tables, cancellation and desktop.');
