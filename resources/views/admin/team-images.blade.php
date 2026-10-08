@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Teams</h1><p>Manage team logos and associated accounts.</p></div></div>

<div class="shell admin-team-images">
  @if(session('notice'))
    <div class="admin-notice">{{ session('notice') }}</div>
  @endif

  <div class="team-image-grid">
    @foreach($teams as $team)
      <section class="card team-image-card">
        <button type="button" class="team-image-preview" data-team-icon-viewer data-team-slug="{{ $team->slug }}" data-team-name="{{ $team->name }}" title="View {{ $team->name }} image">
          <img src="{{ \App\Support\TeamImages::url($team->slug,160) }}" data-full-src="{{ \App\Support\TeamImages::url($team->slug) }}" loading="lazy" decoding="async" alt="{{ $team->name }}">
        </button>
        <div class="team-image-info">
          <h2><a href="/teams/current/{{ $team->slug }}">{{ $team->name }}</a></h2>
          @if($team->account)
            <div class="team-account-linked"><span class="pill">Account linked</span><strong>{{ $team->account->account_name }}</strong><span>{{ $team->account->account_email }}</span></div>
            <form action="/admin/teams/{{ rawurlencode($team->account->fantasy_team_id) }}/unlink" method="post" class="team-account-unlink">
              @csrf
              <input type="hidden" name="user_id" value="{{ $team->account->user_id }}">
              <button type="submit" class="button" data-loading-text="Unlinking…">Unlink account</button>
            </form>
          @else
            <div class="team-account-unlinked subtle">No account associated</div>
          @endif
          <form class="team-image-upload" data-team-image-form data-team-slug="{{ $team->slug }}">
            <label class="button">
              Change Image
              <input type="file" name="image" accept="image/png,image/jpeg,image/webp" hidden required>
            </label>
            <button class="button primary" type="submit">Upload</button>
          </form>
          <div class="team-image-status" aria-live="polite"></div>
        </div>
      </section>
    @endforeach
  </div>
</div>

@push('styles')
<style>
.admin-team-images{padding-bottom:32px}.admin-notice{margin:0 0 16px;padding:10px 12px;border:1px solid var(--badge-green-line);border-radius:9px;background:var(--badge-green-bg);color:var(--badge-green-text);font-weight:700}.team-image-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:18px}.team-image-card{display:flex;align-items:center;gap:14px;padding:14px}.team-image-preview{appearance:none;width:96px;height:96px;flex:0 0 96px;padding:0;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:var(--text);cursor:zoom-in}.team-account-linked{display:grid;gap:3px;font-size:11px;margin-top:8px;overflow-wrap:anywhere}.team-account-linked .pill{width:fit-content;padding:2px 6px;font-size:10px}.team-account-linked>span:last-child{color:var(--muted)}.team-account-unlinked{font-size:11px;margin-top:7px}.team-account-unlink{margin:8px 0}.team-account-unlink .button{padding:5px 8px;font-size:11px;background:var(--panel-2);color:var(--danger);border-color:var(--line);cursor:pointer}.team-image-preview img{display:block;width:100%;height:100%;object-fit:contain}.team-image-info{min-width:0;flex:1}.team-image-info h2{margin:0 0 3px;font-size:16px;overflow-wrap:anywhere}.team-image-upload{display:flex;gap:7px;align-items:center;margin-top:10px}.team-image-upload .button{font-size:11px;padding:7px 9px;cursor:pointer}.team-image-status{min-height:16px;margin-top:5px;font-size:10px;font-weight:700;color:var(--success)}html[data-theme="dark"] .team-image-preview{background:var(--panel-2)}@media(max-width:900px){.team-image-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.team-image-grid{grid-template-columns:1fr}.team-image-card{padding:12px}.team-image-preview{width:82px;height:82px;flex-basis:82px}}
</style>
@endpush

<script>
document.addEventListener('DOMContentLoaded',()=>{
  let csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  document.querySelectorAll('[data-team-image-form]').forEach(form=>{
    const input=form.querySelector('input[type="file"]');
    const submit=form.querySelector('button[type="submit"]');
    const status=form.parentElement.querySelector('.team-image-status');
    input?.addEventListener('change',()=>{if(status)status.textContent=input.files?.[0]?.name||'';});
    form.addEventListener('submit',async event=>{
      event.preventDefault();
      if(submit.disabled)return;
      const file=input?.files?.[0];
      if(!file)return;
      if(file.size>2*1024*1024){status.textContent='Image must be 2 MB or smaller.';return;}
      const data=new FormData();data.append('image',file);
      submit.disabled=true;submit.textContent='Uploading...';status.textContent='';
      try{
        const upload=token=>fetch('/team-icons/'+encodeURIComponent(form.dataset.teamSlug),{
          method:'POST',
          credentials:'same-origin',
          headers:{'X-CSRF-TOKEN':token,'Accept':'application/json'},
          body:data
        });
        let response=await upload(csrf);
        if(response.status===419){
          const tokenResponse=await fetch('/csrf-token',{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}});
          const tokenData=await tokenResponse.json().catch(()=>({}));
          if(tokenResponse.ok&&tokenData.token){
            csrf=tokenData.token;document.querySelector('meta[name="csrf-token"]')?.setAttribute('content',tokenData.token);
            response=await upload(tokenData.token);
          }
        }
        const result=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(result.message||'Upload failed.');
        const fresh=(result.url||('/team-icons/'+form.dataset.teamSlug))+(String(result.url||'').includes('?')?'&':'?')+'t='+Date.now();
        document.querySelectorAll('[data-team-slug="'+CSS.escape(form.dataset.teamSlug)+'"] img').forEach(img=>{img.dataset.fullSrc=fresh;img.removeAttribute('srcset');img.src=result.thumbnail_url||fresh;});
        status.textContent='Image updated.';
        input.value='';
      }catch(error){status.textContent=error.message||'Upload failed.';}
      finally{submit.disabled=false;submit.textContent='Upload';}
    });
  });
});
</script>
@endsection
