const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const panels=['notification-panel','live-score-updates'].map(id=>({id,style:{setProperty(){}},handlers:{},closed:0,querySelector(){return{click:()=>this.closed++}},addEventListener(type,handler){this.handlers[type]=handler}}));
vm.runInNewContext(fs.readFileSync('public/update-sheets.js','utf8'),{document:{getElementById:id=>panels.find(p=>p.id===id),querySelector:()=>null,addEventListener(){}},window:{innerHeight:800,addEventListener(){}},Date});
const touch=(x,y)=>({identifier:1,clientX:x,clientY:y});
const target=(heading,list)=>({closest(selector){return selector.startsWith('button')?null:selector.startsWith('.communication-panel-heading')?heading:list}});
for(const panel of panels){
 const swipe=(start,end,source)=>{panel.handlers.touchstart({target:source,touches:[touch(...start)]});panel.handlers.touchend({changedTouches:[touch(...end)]})};
 swipe([100,200],[105,100],target({},null));assert.equal(panel.closed,1);
 swipe([100,200],[105,250],target({},null));assert.equal(panel.closed,1);
 swipe([100,200],[220,120],target({},null));assert.equal(panel.closed,1);
 swipe([100,200],[100,100],target(null,{scrollTop:0,clientHeight:200,scrollHeight:500}));assert.equal(panel.closed,1,'Scrollable content must scroll');
 swipe([100,200],[100,100],target(null,{scrollTop:300,clientHeight:200,scrollHeight:500}));assert.equal(panel.closed,2,'List at bottom can dismiss');
 panel.handlers.touchstart({target:target({},null),touches:[touch(100,200)]});panel.handlers.touchcancel();panel.handlers.touchend({changedTouches:[touch(100,100)]});assert.equal(panel.closed,2);
}
console.log('Swipe checks passed for both panels: upward dismissal, scrolling, direction and cancelled gestures.');
