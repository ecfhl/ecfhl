const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/team-image-viewer.js','utf8');
function setup({owned='alpha',admin=false}={}){
  const listeners={},elements={},document={activeElement:null,body:{style:{removeProperty(key){delete this[key]}}},
    head:{querySelector:()=>({setAttribute(){}}),querySelectorAll:()=>[]},getElementById:id=>elements[id]||null,querySelector:()=>null,querySelectorAll:()=>[],
    addEventListener(type,fn){(listeners[type]??=[]).push(fn)}};
  function element(id,dataset={}){
    const classes=new Set(),events={};
    const el={dataset,hidden:false,disabled:false,events,classList:{add(...names){names.forEach(x=>classes.add(x))},remove(...names){names.forEach(x=>classes.delete(x))},contains:name=>classes.has(name)},
      addEventListener(type,fn){events[type]=fn},setAttribute(key,value){this[key]=value},removeAttribute(key){delete this[key]},
      focus(){document.activeElement=this},closest:()=>null,getClientRects:()=>[{}],querySelectorAll:()=>[]};
    elements[id]=el;return el;
  }
  const modal=element('team-icon-modal',{ownedTeamSlug:owned,isAdmin:admin?'1':'0'}),image=element('team-icon-modal-image'),title=element('team-icon-modal-title'),view=element('team-icon-modal-view-team'),close=element('team-icon-modal-close'),upload=element('team-icon-modal-upload'),file=element('team-icon-modal-file');
  modal.contains=()=>false;modal.querySelectorAll=()=>[view,upload,close];
  vm.runInNewContext(source,{document,alert(){throw new Error('Unexpected alert')},CSS:{escape:x=>x}});
  listeners.DOMContentLoaded[0]();
  function trigger(name,slug,advisorKey=''){
    const img={dataset:{fullSrc:'/full/'+slug},src:'/thumb/'+slug,alt:name+' logo'};
    const button=element('trigger-'+slug,{teamName:name,teamSlug:slug,advisorKey});button.querySelector=()=>img;button.hasAttribute=key=>key==='data-league-logo'&&slug==='league';
    const event={target:{closest:()=>button},preventDefault(){this.prevented=true}};
    listeners.click[0](event);assert.ok(event.prevented);return button;
  }
  const key=event=>listeners.keydown[0](event);
  return {modal,image,title,view,close,upload,file,trigger,document,key,element};
}
const ui=setup();
const first=ui.trigger('Alpha','alpha');
assert.equal(ui.title.textContent,'Alpha');assert.equal(ui.image.src,'/full/alpha');assert.equal(ui.view.href,'/teams/current/alpha');assert.equal(ui.view.hidden,false);assert.equal(ui.upload.hidden,false);assert.equal(ui.file.disabled,false);
assert.ok(ui.modal.classList.contains('open')&&ui.modal.classList.contains('loading'));assert.equal(ui.document.body.style.overflow,'hidden');assert.equal(ui.document.activeElement,ui.close);
ui.image.onload();assert.equal(ui.modal.classList.contains('loading'),false);
ui.modal.events.click({stopPropagation(){},target:ui.image});assert.ok(ui.modal.classList.contains('open'));
ui.close.events.click({stopPropagation(){}});assert.equal(ui.modal.classList.contains('open'),false);assert.equal(ui.modal['aria-hidden'],'true');assert.equal(ui.document.activeElement,first);assert.equal(ui.document.body.style.overflow,undefined);
// A logo inserted after initialization must work without registering another handler.
const next=ui.trigger('Beta & Sons','beta');assert.equal(ui.title.textContent,'Beta & Sons');assert.equal(ui.view.href,'/teams/current/beta');assert.ok(ui.modal.classList.contains('loading'));assert.equal(ui.upload.hidden,true);assert.equal(ui.file.disabled,true);
ui.modal.events.click({stopPropagation(){},target:ui.modal});assert.equal(ui.modal.classList.contains('open'),false);assert.equal(ui.document.activeElement,next);
ui.trigger('Alpha','alpha');const card=ui.element('card');card.classList.add('team-icon-modal-card');ui.modal.events.click({stopPropagation(){},target:card});assert.equal(ui.modal.classList.contains('open'),true);ui.close.events.click({stopPropagation(){}});
ui.trigger('Alpha','alpha');ui.key({key:'Escape'});assert.equal(ui.modal.classList.contains('open'),false);
ui.trigger('Alpha','alpha');let prevented=false;ui.key({key:'Tab',preventDefault(){prevented=true}});assert.ok(prevented);assert.equal(ui.document.activeElement,ui.view);
prevented=false;ui.key({key:'Tab',shiftKey:true,preventDefault(){prevented=true}});assert.ok(prevented);assert.equal(ui.document.activeElement,ui.close);
ui.trigger('Advisor','lineup-advisor','mike');assert.equal(ui.view.hidden,true);assert.equal(ui.view.href,undefined);assert.equal(ui.upload.hidden,true);assert.equal(ui.file.disabled,true);
ui.close.events.click({stopPropagation(){}});
for(let i=0;i<3;i++){
 const logo=ui.trigger('ECFHL','league');assert.equal(ui.title.textContent,'East Coast Fantasy Hockey League');assert.equal(ui.view.hidden,true);assert.equal(ui.upload.hidden,true);assert.equal(ui.file.disabled,true);
 ui.modal.events.click({stopPropagation(){},target:ui.image});assert.equal(ui.modal.classList.contains('open'),true);ui.modal.events.click({stopPropagation(){},target:ui.modal});assert.equal(ui.modal.classList.contains('open'),false);assert.equal(ui.document.activeElement,logo);
}
const admin=setup({admin:true});admin.trigger('Beta','beta');assert.equal(admin.upload.hidden,false);
console.log('Team image viewer checks passed: titles, correct team links, dynamically loaded logos, fresh image loading, close/backdrop/Escape, focus trapping and upload permissions.');
