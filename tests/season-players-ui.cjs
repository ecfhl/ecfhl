const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const run=(source,context)=>vm.runInNewContext(source,{window:{addEventListener(){},removeEventListener(){}},...context});
const make=()=>{const rows={html:'',insertAdjacentHTML(_,html){this.html+=html}},count={},error={},button={dataset:{nextUrl:'/players?positions=F,D&page=2'},disabled:false,addEventListener(_,fn){this.click=fn}};return {rows,count,error,button,document:{addEventListener(){},removeEventListener(){},querySelector:()=>null,getElementById:id=>({'season-player-more':button,'season-player-rows':rows,'season-player-count':count,'season-player-error':error}[id])}}};
(async()=>{
 const pinned=make();let width=44,rankWidth=36,offset='',resize;const scroll={style:{setProperty(key,value){assert.equal(key,'--player-sticky-offset');offset=value}}};const team={getBoundingClientRect:()=>({width})},rank={getBoundingClientRect:()=>({width:rankWidth})};pinned.document.querySelector=()=>({querySelector:s=>{assert.equal(s,'thead .player-frozen-team');return team},closest:()=>scroll});
 run(fs.readFileSync('public/season-players.js','utf8'),{document:pinned.document,ResizeObserver:class {constructor(fn){resize=fn} observe(node){assert.ok([team,rank].includes(node))}}});assert.equal(offset,'44px');width=170;resize();assert.equal(offset,'170px');rankWidth=40;resize();assert.equal(offset,'170px');
 // The header follows page scroll below navigation and syncs horizontal scroll both ways.
 const page=make();let tableTop=100,tableBottom=800;const events={},scrollEvents={},frozenEvents={},vars={'--rank-column-width':'36px','--player-column-width':'170px','--team-column-width':'170px','--player-stat-width':'72px','--player-stat-count':'12','--player-sticky-offset':'206px'};
 const style=()=>({setProperty(key,value){this[key]=value}});
 const scroller={style:style(),scrollLeft:0,clientWidth:700,getBoundingClientRect:()=>({left:20,top:tableTop,bottom:tableBottom}),addEventListener:(key,fn)=>scrollEvents[key]=fn};
 const frozen={style:style(),scrollLeft:0,append(child){this.child=child},addEventListener:(key,fn)=>frozenEvents[key]=fn};
 const head={cloneNode:()=>({type:'head'}),getBoundingClientRect:()=>({top:tableTop,height:30})},colgroup={cloneNode:()=>({type:'cols'})};
 const clone={style:style(),append(...children){this.children=children}};
 const fullTable={closest:()=>scroller,cloneNode:()=>clone,getBoundingClientRect:()=>({width:1300}),querySelector:key=>key==='thead'?head:key==='colgroup'?colgroup:key.includes('rank')?rank:team};
 const originalGet=page.document.getElementById;page.document.getElementById=id=>id==='season-player-fixed-header'?frozen:originalGet(id);
 page.document.querySelector=key=>key==='.season-player-table'?fullTable:key==='.site-header'?{getBoundingClientRect:()=>({bottom:60})}:null;
 run(fs.readFileSync('public/season-players.js','utf8'),{document:page.document,window:{addEventListener:(key,fn)=>events[key]=fn},ResizeObserver:class {constructor(){} observe(){}},getComputedStyle:()=>({getPropertyValue:key=>vars[key]}),requestAnimationFrame:fn=>fn()});
 assert.equal(frozen.hidden,true);assert.equal(clone.children[0].type,'cols');assert.equal(clone.children[1].type,'head');
 tableTop=20;events.scroll();assert.equal(frozen.hidden,false);assert.equal(frozen.style.top,'60px');assert.equal(frozen.style.width,'700px');assert.equal(frozen.style.left,'20px');assert.equal(clone.style.width,'1300px');
 scroller.scrollLeft=300;scrollEvents.scroll();assert.equal(frozen.scrollLeft,300);frozen.scrollLeft=450;frozenEvents.scroll();assert.equal(scroller.scrollLeft,450);
 scroller.clientWidth=350;vars['--team-column-width']='44px';events.resize();assert.equal(frozen.style.width,'350px');assert.equal(frozen.style['--team-column-width'],'44px');
 tableBottom=80;events.scroll();assert.equal(frozen.hidden,true);tableTop=100;tableBottom=800;events.scroll();assert.equal(frozen.hidden,true);
 const ui=make();let calls=0,release;const fetch=async(url,options)=>{calls++;assert.equal(url,'/players?positions=F,D&page=2');assert.equal(options.headers.Accept,'application/json');await new Promise(r=>release=r);return {ok:true,json:async()=>({html:'<tr>next</tr>',shown:50,total:50,next_url:null})}};
 run(fs.readFileSync('public/season-players.js','utf8'),{document:ui.document,fetch});const pending=ui.button.click();await ui.button.click();assert.equal(calls,1);assert.equal(ui.button.disabled,true);release();await pending;assert.equal(ui.rows.html,'<tr>next</tr>');assert.equal(ui.count.textContent,'Showing 50 of 50 players');assert.equal(ui.button.hidden,true);assert.equal(ui.button.disabled,false);
 const failure=make();run(fs.readFileSync('public/season-players.js','utf8'),{document:failure.document,fetch:async()=>({ok:false})});await failure.button.click();assert.equal(failure.rows.html,'');assert.equal(failure.button.dataset.nextUrl,'/players?positions=F,D&page=2');assert.equal(failure.button.disabled,false);assert.match(failure.error.textContent,/try again/);
 console.log('Season players UI checks passed: page-frozen header, two-way horizontal sync, responsive widths and boundaries, frozen-column offsets, preserved filters, duplicate-click protection, append/count/end and retry after failure.');
})().catch(e=>{console.error(e);process.exit(1)});
