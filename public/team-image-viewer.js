document.addEventListener('DOMContentLoaded',()=>{
  const ensureLink=(selector,attrs)=>{
    let link=document.head.querySelector(selector);
    if(!link){link=document.createElement('link');document.head.appendChild(link);}
    Object.entries(attrs).forEach(([key,value])=>link.setAttribute(key,value));
    return link;
  };
  ensureLink('link[rel="manifest"]',{rel:'manifest',href:'/site.webmanifest?v=8'});
  ensureLink('link[rel="apple-touch-icon"]',{rel:'apple-touch-icon',href:'/ecfhl-logo.png?v=8'});
  document.head.querySelectorAll('link[rel="icon"],link[rel="shortcut icon"]').forEach(link=>{link.setAttribute('href','/ecfhl-logo.png?v=8');link.setAttribute('type','image/png');});

  const modal=document.getElementById('team-icon-modal');
  const modalImage=document.getElementById('team-icon-modal-image');
  const modalTitle=document.getElementById('team-icon-modal-title');
  const viewTeamButton=document.getElementById('team-icon-modal-view-team');
  const closeButton=document.getElementById('team-icon-modal-close');
  const uploadButton=document.getElementById('team-icon-modal-upload');
  const saveButton=document.getElementById('team-icon-modal-save');
  const fileInput=document.getElementById('team-icon-modal-file');
  const advisorNameRow=document.getElementById('team-icon-advisor-name');
  const advisorNameInput=document.getElementById('team-icon-advisor-input');
  const advisorNameSave=document.getElementById('team-icon-advisor-save');
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const ownedTeamSlug=modal?.dataset.ownedTeamSlug||'';
  const isAdmin=modal?.dataset.isAdmin==='1';
  if(!modal||!modalImage||!closeButton||!fileInput)return;


  let lastTrigger=null;
  let activeSlug='';
  let activeAdvisorKey='';
  let viewingLeagueLogo=false;
  let pendingLeagueFile=null;

  const closeModal=()=>{
    modal.classList.remove('open','loading');
    modal.setAttribute('aria-hidden','true');
    document.body.style.removeProperty('overflow');
    fileInput.value='';
    fileInput.disabled=false;
    if(uploadButton)uploadButton.hidden=true;
    if(saveButton)saveButton.hidden=true;
    pendingLeagueFile=null;
    activeAdvisorKey='';
    viewingLeagueLogo=false;
    advisorNameRow?.classList.remove('open');
    const trigger=lastTrigger;
    lastTrigger=null;
    trigger?.focus({preventScroll:true});
  };

  document.addEventListener('click',event=>{
      const teamButton=event.target.closest('[data-team-icon-viewer]');
      if(!teamButton||modal.contains(teamButton))return;
      event.preventDefault();
      const button=teamButton;
      const img=teamButton.querySelector('img');
      if(!img)return;
      viewingLeagueLogo=button.hasAttribute('data-league-logo');
      lastTrigger=button;
      activeSlug=button.dataset.teamSlug||'';
      activeAdvisorKey=button.dataset.advisorKey||'';
      if(modalTitle)modalTitle.textContent=(viewingLeagueLogo?'East Coast Fantasy Hockey League':button.dataset.teamName)||button.dataset.advisorFirstName||img.alt||'Team logo';
      if(viewTeamButton){
        viewTeamButton.hidden=viewingLeagueLogo||!activeSlug||!!activeAdvisorKey;
        if(viewTeamButton.hidden)viewTeamButton.removeAttribute('href');
        else viewTeamButton.href='/teams/current/'+encodeURIComponent(activeSlug);
      }
      const fullSrc=img.dataset.fullSrc||img.currentSrc||img.src;
      modal.classList.add('loading');
      modalImage.removeAttribute('src');
      modalImage.alt=img.alt||'Team icon';
      modalImage.onload=()=>modal.classList.remove('loading');
      modalImage.onerror=()=>modal.classList.remove('loading');
      modalImage.src=fullSrc;
      if(advisorNameRow&&advisorNameInput){
        advisorNameInput.value='';
        advisorNameRow.classList.remove('open');
      }
      if(viewingLeagueLogo){
        if(uploadButton)uploadButton.hidden=!isAdmin;
        if(saveButton)saveButton.hidden=true;
        fileInput.disabled=!isAdmin;
      }else if(activeAdvisorKey){
        if(uploadButton)uploadButton.hidden=true;
        fileInput.disabled=true;
      }else{
        const canChangeImage=!!activeSlug && (isAdmin || ownedTeamSlug===activeSlug);
        if(uploadButton)uploadButton.hidden=!canChangeImage;
        fileInput.disabled=!canChangeImage;
      }
      modal.classList.add('open');
      modal.setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
      closeButton.focus();
  });

  advisorNameSave?.addEventListener('click',async()=>{
    if(advisorNameSave.disabled)return;
    if(!activeAdvisorKey||!advisorNameInput)return;
    const firstName=advisorNameInput.value.trim();
    if(!firstName){
      alert('First name is required.');
      advisorNameInput.focus();
      return;
    }

    advisorNameSave.disabled=true;
    advisorNameSave.textContent='Saving...';
    try{
      const response=await fetch('/lineup-advisors/'+encodeURIComponent(activeAdvisorKey)+'/profile',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body:JSON.stringify({first_name:firstName})
      });
      if(!response.ok){
        let message='Could not save advisor name.';
        try{const data=await response.json();message=data.message||message;}catch(e){}
        throw new Error(message);
      }
      const data=await response.json();
      const savedName=data.first_name||firstName;
      document.querySelectorAll('[data-advisor-display-name="'+CSS.escape(activeAdvisorKey)+'"]').forEach(el=>el.textContent=savedName);
      document.querySelectorAll('[data-team-icon-viewer][data-advisor-key="'+CSS.escape(activeAdvisorKey)+'"]').forEach(button=>{
        button.dataset.advisorFirstName=savedName;
        button.title='View '+savedName+' advisor image';
        button.setAttribute('aria-label','View '+savedName+' Lineup Advisor image');
        const img=button.querySelector('img');
        if(img)img.alt=savedName+', Lineup Advisor';
      });
    }catch(error){
      alert(error.message||'Could not save advisor name.');
    }finally{
      advisorNameSave.disabled=false;
      advisorNameSave.textContent='Save';
    }
  });

  uploadButton?.addEventListener('click',()=>fileInput.click());

  const uploadSelectedFile=async(file)=>{
    if(!file||(!viewingLeagueLogo&&!activeSlug))return;
    const form=new FormData();
    form.append('image',file);
    if(uploadButton)uploadButton.disabled=true;
    if(saveButton)saveButton.disabled=true;
    const actionButton=viewingLeagueLogo?saveButton:uploadButton;
    const oldText=actionButton?.textContent||'';
    if(actionButton)actionButton.textContent='Saving...';
    try{
      const uploadUrl=viewingLeagueLogo?'/team-icons/league-logo':'/team-icons/'+encodeURIComponent(activeSlug);
      const response=await fetch(uploadUrl,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:form});
      if(!response.ok){
        let message='Could not save image.';
        try{const data=await response.json();message=data.message||message;}catch(e){}
        throw new Error(message);
      }
      const data=await response.json();
      const stamp='t='+Date.now();
      const freshUrl=(data.url||(viewingLeagueLogo?'/team-icons/league-logo':'/team-icons/'+activeSlug))+(String(data.url||'').includes('?')?'&':'?')+stamp;
      modalImage.src=freshUrl;
      if(viewingLeagueLogo){
        document.querySelectorAll('[data-league-logo] img').forEach(img=>{
          img.dataset.fullSrc=freshUrl;
          img.removeAttribute('srcset');
          const thumb=data.thumbnail_url||'/team-icons/league-logo/thumbnail?size=160';
          img.src=thumb+(thumb.includes('?')?'&':'?')+stamp;
        });
        pendingLeagueFile=null;
        if(saveButton)saveButton.hidden=true;
        if(uploadButton)uploadButton.textContent='Change Image';
      }else{
        document.querySelectorAll('[data-team-icon-viewer][data-team-slug="'+CSS.escape(activeSlug)+'"] img').forEach(img=>{
          img.dataset.fullSrc=freshUrl;img.removeAttribute('srcset');
          img.src=data.thumbnail_url||('/team-icons/'+encodeURIComponent(activeSlug)+'/thumbnail?size=160&'+stamp);
        });
      }
    }catch(error){
      alert(error.message||'Could not save image.');
    }finally{
      if(uploadButton)uploadButton.disabled=false;
      if(saveButton)saveButton.disabled=false;
      if(actionButton)actionButton.textContent=oldText;
      fileInput.value='';
    }
  };

  fileInput.addEventListener('change',()=>{
    const file=fileInput.files?.[0];
    if(!file)return;
    if(file.size>2*1024*1024){
      alert('Image must be 2 MB or smaller.');
      fileInput.value='';
      return;
    }
    if(viewingLeagueLogo){
      pendingLeagueFile=file;
      const preview=URL.createObjectURL(file);
      modalImage.onload=()=>URL.revokeObjectURL(preview);
      modalImage.src=preview;
      if(saveButton)saveButton.hidden=false;
      if(uploadButton)uploadButton.textContent='Choose Different Image';
      return;
    }
    uploadSelectedFile(file);
  });

  saveButton?.addEventListener('click',()=>{
    if(pendingLeagueFile)uploadSelectedFile(pendingLeagueFile);
  });

  closeButton.addEventListener('click',event=>{event.stopPropagation();closeModal();});
  modal.addEventListener('click',event=>{
    event.stopPropagation();
    if(event.target.closest('button,a,input,label'))return;
    if(viewingLeagueLogo||event.target===modal||event.target.classList.contains('team-icon-modal-card'))closeModal();
  });
  document.addEventListener('keydown',event=>{
    if(!modal.classList.contains('open'))return;
    if(event.key==='Escape')closeModal();
    if(event.key==='Tab'){
      const controls=[...modal.querySelectorAll('a[href],button,input:not([type="file"])')].filter(el=>!el.hidden&&!el.disabled&&el.getClientRects().length);
      const first=controls[0],last=controls[controls.length-1];
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
    }
  });
});
