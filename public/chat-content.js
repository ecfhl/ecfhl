(()=>{
 const node=(tag,text,cls)=>{const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(cls)el.className=cls;return el;};
 const safeUrl=value=>{try{const url=new URL(value);return url.protocol==='https:'?url.href:null;}catch{return null;}};
 const inline=(host,text)=>{
  const pattern=/(\*\*([^*]+)\*\*|\*([^*\n]+)\*|~~([^~]+)~~|\[([^\]]+)\]\((https:\/\/[^\s)]+)\))/g;let start=0,match;
  while((match=pattern.exec(text))){host.append(document.createTextNode(text.slice(start,match.index)));let el;
   if(match[2])el=node('strong',match[2]);else if(match[3])el=node('em',match[3]);else if(match[4])el=node('s',match[4]);else{el=node('a',match[5]);const url=safeUrl(match[6]);if(url){el.href=url;el.target='_blank';el.rel='noopener noreferrer';}}
   host.append(el);start=pattern.lastIndex;
  }host.append(document.createTextNode(text.slice(start)));
 };
 const body=message=>{
  const host=node('div',undefined,'chat-message-body');let list=null;
  String(message.body||'').split('\n').forEach(line=>{
   const image=line.match(/^!\[(.*?)\]\((https:\/\/[^\s)]+)\)$/);
   if(image&&safeUrl(image[2])){const img=node('img');img.src=safeUrl(image[2]);img.alt=image[1]||'Shared image';img.loading='lazy';img.referrerPolicy='no-referrer';img.className='chat-shared-image';host.append(img);list=null;return;}
   if(line.startsWith('- ')){if(!list){list=node('ul');host.append(list);}const li=node('li');inline(li,line.slice(2));list.append(li);return;}
   list=null;const p=node('p');inline(p,line);host.append(p);
  });
  if(message.attachment_url){const img=node('img');img.src=message.attachment_url;img.alt='Shared image';img.loading='lazy';img.className='chat-shared-image';host.append(img);}return host;
 };
 const reaction=(message,react)=>{const button=node('button','👍'+(message.likes?' '+message.likes:''),'chat-reaction');button.type='button';button.setAttribute('aria-pressed',String(Boolean(message.liked)));button.setAttribute('aria-label',(message.liked?'Remove':'Add')+' thumbs up'+(message.likes?', '+message.likes+' reactions':''));button.addEventListener('click',async()=>{button.disabled=true;try{await react(!message.liked);}finally{button.disabled=false;}});return button;};
 window.EcfhlChatContent={body,reaction};
})();
