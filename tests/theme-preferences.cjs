const vm=require('node:vm'),fs=require('node:fs'),assert=require('node:assert/strict');
function setup(account){
 const listeners={},elements={};
 for(const id of ['profile-theme','theme-save-status','theme-save-retry'])elements[id]={hidden:true,addEventListener(type,fn){this[type]=fn}};
 const root={dataset:{theme:'dark',themeAccount:account?'1':''}},stored=[],requests=[];
 let fail=false;
 const context={document:{documentElement:root,getElementById:id=>elements[id],querySelector:()=>({content:'csrf'}),addEventListener(type,fn){listeners[type]=fn}},localStorage:{setItem:(...v)=>stored.push(v)},window:{syncThemeControl(){}},fetch:async(url,options)=>{requests.push({url,options});const theme=JSON.parse(options.body).theme;return{ok:!fail,json:async()=>({preferences:{theme}})}}};
 vm.runInNewContext(fs.readFileSync('public/theme-preferences.js','utf8'),context);listeners.DOMContentLoaded();
 return{...context,elements,root,stored,requests,setFailure(value){fail=value}};
}
(async()=>{
 const guest=setup(false);assert.equal(guest.root.dataset.theme,'dark');guest.window.EcfhlTheme.set('light');assert.equal(guest.stored[0][1],'light');assert.equal(guest.requests.length,0);
 const owner=setup(true);owner.window.EcfhlTheme.set('light');await new Promise(r=>setImmediate(r));assert.equal(owner.requests[0].url,'/account/preferences');assert.equal(owner.requests[0].options.headers['X-CSRF-TOKEN'],'csrf');assert.equal(owner.stored.length,0);assert.equal(owner.elements['profile-theme'].value,'light');assert.match(owner.elements['theme-save-status'].textContent,/saved/);
 owner.setFailure(true);owner.window.EcfhlTheme.set('dark');await new Promise(r=>setImmediate(r));assert.equal(owner.elements['theme-save-retry'].hidden,false);
 owner.setFailure(false);owner.elements['theme-save-retry'].click();await new Promise(r=>setImmediate(r));assert.equal(owner.elements['theme-save-retry'].hidden,true);assert.match(owner.elements['theme-save-status'].textContent,/saved/);
 console.log('Theme preference checks passed: dark default, guest storage, account saving, control synchronization and retry.');
})().catch(error=>{console.error(error);process.exit(1)});
