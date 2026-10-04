document.addEventListener('DOMContentLoaded',()=>{
  const modal=document.getElementById('team-icon-modal');
  const modalImage=document.getElementById('team-icon-modal-image');
  const modalTitle=document.getElementById('team-icon-modal-title');
  const viewTeamButton=document.getElementById('team-icon-modal-view-team');
  const closeButton=document.getElementById('team-icon-modal-close');
  const uploadButton=document.getElementById('team-icon-modal-upload');
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

  const closeModal=()=>{
    modal.classList.remove('open','loading');
    modal.setAttribute('aria-hidden','true');
    document.body.style.removeProperty('overflow');
    fileInput.value='';
    fileInput.disabled=false;
    if(uploadButton)uploadButton.hidden=true;
    activeAdvisorKey='';
    advisorNameRow?.classList.remove('open');
    lastTrigger?.focus();
  };

  document.addEventListener('click',event=>{
      const button=event.target.closest('[data-team-icon-viewer]');
      if(!button)return;
      event.preventDefault();
      const img=button.querySelector('img');
      if(!img)return;
      lastTrigger=button;
      activeSlug=button.dataset.teamSlug||'';
      activeAdvisorKey=button.dataset.advisorKey||'';
      if(modalTitle)modalTitle.textContent=button.dataset.teamName||button.dataset.advisorFirstName||img.alt||'Team logo';
      if(viewTeamButton){
        viewTeamButton.hidden=!activeSlug||!!activeAdvisorKey;
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
      if(activeAdvisorKey){
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
  fileInput.addEventListener('change',async()=>{
    const file=fileInput.files?.[0];
    if(!file||!activeSlug)return;
    if(file.size>2*1024*1024){
      alert('Team icon must be 2 MB or smaller.');
      fileInput.value='';
      return;
    }

    const form=new FormData();
    form.append('image',file);
    if(uploadButton)uploadButton.disabled=true;
    if(uploadButton)uploadButton.textContent='Uploading...';

    try{
      const response=await fetch('/team-icons/'+encodeURIComponent(activeSlug),{
        method:'POST',
        headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body:form
      });
      if(!response.ok){
        let message='Could not upload team icon.';
        try{const data=await response.json();message=data.message||message;}catch(e){}
        throw new Error(message);
      }
      const data=await response.json();
      const freshUrl=(data.url||('/team-icons/'+activeSlug))+(String(data.url||'').includes('?')?'&':'?')+'t='+Date.now();
      modalImage.src=freshUrl;
      document.querySelectorAll('[data-team-icon-viewer][data-team-slug="'+CSS.escape(activeSlug)+'"] img').forEach(img=>{img.dataset.fullSrc=freshUrl;img.removeAttribute('srcset');img.src=data.thumbnail_url||('/team-icons/'+encodeURIComponent(activeSlug)+'/thumbnail?size=160&t='+Date.now());});
    }catch(error){
      alert(error.message||'Could not upload team icon.');
    }finally{
      if(uploadButton)uploadButton.disabled=false;
      if(uploadButton)uploadButton.textContent='Change Image';
      fileInput.value='';
    }
  });

  closeButton.addEventListener('click',closeModal);
  modal.addEventListener('click',event=>{if(event.target===modal||event.target.classList.contains('team-icon-modal-card'))closeModal();});
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
