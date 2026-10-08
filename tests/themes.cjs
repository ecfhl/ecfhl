const assert = require('node:assert/strict');
const fs = require('node:fs');
const css = (fs.readFileSync('public/app.css','utf8')+'\n'+fs.readFileSync('public/themes.css','utf8')).replace(/\/\*[\s\S]*?\*\//g,'');
function variables(theme){
  const values={};
  for(const [,selector,body] of css.matchAll(/([^{}]+)\{([^{}]*)\}/g)){
    if(selector.trim()===':root'||(theme==='dark'&&selector.trim()==='html[data-theme="dark"]')){
      for(const [,name,value] of body.matchAll(/(--[\w-]+)\s*:\s*([^;]+);?/g))values[name]=value.trim();
    }
  }
  const resolve=v=>v.startsWith('var(')?resolve(values[v.slice(4,-1)]):v;
  return name=>resolve(values[name]);
}
function luminance(hex){
  if(hex.length===4)hex='#'+[...hex.slice(1)].map(x=>x+x).join('');
  const rgb=[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16)/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4);
  return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;
}
for(const theme of ['light','dark']){
  const get=variables(theme);
  const pairs=['--bg','--panel','--panel-2','--selection-bg','--hover-bg','--game-live','--game-finished','--game-upcoming','--ir-bg','--player-defense-bg','--player-goalie-bg'].flatMap(bg=>['--text','--muted'].map(text=>[text,bg]));
  pairs.push(['--ir-text','--ir-bg'],['--heading-text','--reserve-ir'],['--accent','--panel'],['--gold','--panel'],['--on-action','--action-bg'],['--on-action','--action-hover'],['--heading-text','--heading-bg'],['--table-heading-text','--table-heading-bg']);
  for(const color of ['green','yellow','orange','red'])pairs.push(['--badge-'+color+'-text','--badge-'+color+'-bg']);
  for(const [text,bg] of pairs){
    const a=luminance(get(text)),b=luminance(get(bg));const contrast=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    assert.ok(contrast>=4.5,`${theme} ${text}/${bg} contrast ${contrast.toFixed(2)} < 4.5`);
  }
}
console.log('Light/dark contrast passed: surfaces, game states, selections, links, actions, table headings and semantic badges meet 4.5:1.');
