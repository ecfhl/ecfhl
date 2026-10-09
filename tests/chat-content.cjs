const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
class Node{constructor(tag,text=''){this.tag=tag;this.textContent=text;this.children=[];this.attrs={};this.handlers={};}append(...children){this.children.push(...children);}setAttribute(k,v){this.attrs[k]=v;}addEventListener(k,v){this.handlers[k]=v;}}
const context={window:{},URL,document:{createElement:tag=>new Node(tag),createTextNode:text=>new Node('#text',text)}};
vm.runInNewContext(fs.readFileSync('public/chat-content.js','utf8'),context);
const walk=node=>[node,...node.children.flatMap(walk)];
const body=context.window.EcfhlChatContent.body({body:'**Bold** *Italic* ~~Gone~~\n- Item\n[Link](https://example.com)\n<script>alert(1)</script>\n![GIF](https://example.com/one.gif)',attachment_url:'/api/messages/1/attachment'});const nodes=walk(body);
for(const tag of ['strong','em','s','ul','li','a'])assert(nodes.some(n=>n.tag===tag),tag+' is supported');
assert(!nodes.some(n=>n.tag==='script'),'HTML never becomes executable');assert(nodes.some(n=>n.tag==='#text'&&n.textContent.includes('<script>')));
assert.equal(nodes.filter(n=>n.tag==='img').length,2);assert.equal(nodes.find(n=>n.tag==='a').rel,'noopener noreferrer');
const unsafe=walk(context.window.EcfhlChatContent.body({body:'![bad](javascript:alert(1))'}));assert(!unsafe.some(n=>n.tag==='img'));
(async()=>{let active;const reaction=context.window.EcfhlChatContent.reaction({likes:2,liked:true},value=>active=value);assert.equal(reaction.textContent,'👍 2');await reaction.handlers.click();assert.equal(active,false);console.log('Chat content passed: formatting, lists, safe links, literal HTML, GIF/image display and reaction removal.');})();
