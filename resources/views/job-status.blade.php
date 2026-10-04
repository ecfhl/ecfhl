@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Data collectors</div><h1>Collector Status</h1><p>Current status of the automated data used by Daily Targets.</p></div></div>
<div class="shell job-status">
    <div class="status-actions">
        <a class="button status-back" href="/daily-targets">← Back to Daily Targets</a>
        <div class="status-action-buttons">
            <form method="POST" action="/job-status/test-scoring-notification" class="test-notification-form">
                @csrf
                <button type="submit" class="button test-notification" title="Test the latest player scoring alert on this device">Send Test Notification</button>
            </form>
            <form method="POST" action="/job-status/run/all" class="run-all-form job-ajax-form" data-job="all">
                @csrf
                <input type="hidden" name="return_to" value="job-status">
                <button type="submit" class="button primary run-all">Refresh Data</button>
            </form>
        </div>
    </div>
    <div id="job-live-results" class="job-live-results" hidden aria-live="polite"><button type="button" class="job-live-close" aria-label="Close message">×</button><pre class="job-live-text"></pre></div>
    <div class="status-grid">
        @foreach($jobs as $job)
        @php
            $jobKey = $job['key'];
            $displaySchedule = $job['schedule'];
        @endphp
        <section class="card status-card" data-job-key="{{ $jobKey }}">
            <div class="status-head"><div><h2>{{ $job['name'] }}</h2><p class="subtle">{{ $displaySchedule }}</p></div>
                @if($job['outcome'])
                  <span class="pill {{ $job['outcome']==='success'?'status-ok':($job['outcome']==='warning'?'status-stale':'status-failed-pill') }}">{{ $job['outcome']==='success'?'Successful':($job['outcome']==='warning'?'Warning':'Failed') }}</span>
                @else
                  <span class="pill {{ $job['state']==='Current'?'status-ok':($job['state']==='No data'?'status-empty':'status-stale') }}">{{ $job['state'] }}</span>
                @endif
            </div>
            <dl>
                <div><dt>Last data update</dt><dd>{{ $job['last_update'] ?? 'Never' }}</dd></div>
                <div><dt>Records</dt><dd>{{ number_format($job['records']) }}</dd></div>
                <div><dt>Next scheduled run</dt><dd>{{ $job['next_run'] }}</dd></div>
            </dl>
            <p class="subtle status-note">{{ $job['description'] }}</p>
            <form method="POST" action="/job-status/run/{{ $jobKey }}" class="run-form job-ajax-form" data-job="{{ $jobKey }}">
                @csrf
                <button type="submit" class="button primary run-now">{{ $jobKey==='projections' ? 'Regenerate Projected FPts' : 'Run Now' }}</button>
            </form>
        </section>
        @endforeach
    </div>
    @if(session('job_success') || session('job_error'))
    <section class="job-results" aria-live="polite">
        <h2>Last Run Result</h2>
        @if(session('job_success'))<div class="job-message job-message-ok">{{ session('job_success') }}</div>@endif
        @if(session('job_error'))<div class="job-message job-message-error">{{ session('job_error') }}</div>@endif
    </section>
    @endif
    <p class="subtle status-footer">Times shown in Atlantic time. “Current” is based on the expected refresh interval; source data itself may not change on every check.</p>
</div>
<style>
.status-actions{margin:20px 0 0;display:flex;align-items:center;justify-content:space-between;gap:12px}.status-action-buttons{display:flex;align-items:center;gap:8px}.test-notification-form{margin:0}.test-notification{cursor:pointer;padding:7px 12px;font-size:12px}.status-back{text-decoration:none}.run-all-form{margin:0}.run-all{cursor:pointer;padding:7px 12px;font-size:12px}.run-all:disabled,.run-now:disabled{opacity:.65;cursor:wait}.status-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin:18px 0 24px}.status-card{padding:20px;display:flex;flex-direction:column;height:100%}.status-card-success{border-color:#86efac;background:#f0fdf4}.status-card-warning{border-color:#fcd34d;background:#fffbeb}.status-card-failed{border-color:#fecaca;background:#fff1f2}.job-outcomes-cleared .status-card{background:#fff!important;border-color:var(--line)!important}.job-outcomes-cleared .status-card.job-run-active.status-card-success{background:#f0fdf4!important;border-color:#86efac!important}.job-outcomes-cleared .status-card.job-run-active.status-card-warning{background:#fffbeb!important;border-color:#fcd34d!important}.job-outcomes-cleared .status-card.job-run-active.status-card-failed{background:#fff1f2!important;border-color:#fecaca!important}.status-failed-pill{background:#fee2e2;color:#b91c1c;border-color:#fecaca}.status-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.status-head h2{margin:0;font-size:19px}.status-head p{margin:4px 0 0}.status-card dl{margin:20px 0 0}.status-card dl div{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-top:1px solid var(--line)}.status-card dt{color:var(--muted);font-size:13px}.status-card dd{margin:0;text-align:right;font-weight:800}.status-ok{background:#dcfce7;color:#166534;border-color:#86efac}.status-stale{background:#fef3c7;color:#92400e;border-color:#fcd34d}.status-empty{background:#e5e7eb;color:#4b5563;border-color:#d1d5db}.status-note{font-size:12px;margin:14px 0 0}.status-footer{font-size:12px;margin-bottom:28px}.run-form{margin-top:auto;padding-top:16px}.run-now{width:100%;cursor:pointer}.job-results{margin:0 0 22px}.job-results h2{font-size:17px;margin:0 0 10px}.job-message,.job-live-results{padding:12px 14px;border-radius:10px;font-weight:700;white-space:pre-wrap}.job-live-results{position:relative;padding-right:42px}.job-live-text{margin:0;font:inherit;white-space:pre-wrap}.job-live-close{position:absolute;top:7px;right:9px;width:26px;height:26px;border:0;border-radius:999px;background:transparent;color:inherit;font-size:22px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}.job-live-close:hover{background:rgba(0,0,0,.08)}.job-message+.job-message{margin-top:10px}.job-message-ok,.job-live-results.ok{background:#dcfce7;color:#166534;border:1px solid #86efac}.job-message-error,.job-live-results.error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}.job-live-results.warning{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}.job-live-results{margin:18px 0 0;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;line-height:1.55}@media(max-width:800px){.status-actions{align-items:flex-start}.status-grid{grid-template-columns:1fr}.status-card{padding:16px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const box=document.getElementById('job-live-results');
 const boxText=box.querySelector('.job-live-text');
 const closeButton=box.querySelector('.job-live-close');
 const storageKey='ecfhl-job-live-results';
 const clearedKey='ecfhl-job-outcomes-cleared';
 const statusRoot=document.querySelector('.job-status');
 if(sessionStorage.getItem(clearedKey)==='1')statusRoot?.classList.add('job-outcomes-cleared');
 const saveBox=()=>sessionStorage.setItem(storageKey,JSON.stringify({
   text:boxText.textContent,
   className:box.className,
   hidden:box.hidden
 }));
 const restoreBox=()=>{
   try{
     const saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');
     if(saved && saved.text){
       boxText.textContent=saved.text;
       box.className=saved.className||'job-live-results';
       box.hidden=false;
     }
   }catch(e){}
 };
 restoreBox();
 closeButton.addEventListener('click',()=>{
   box.hidden=true;
   box.className='job-live-results';
   boxText.textContent='';
   sessionStorage.removeItem(storageKey);
   sessionStorage.setItem(clearedKey,'1');
   statusRoot?.classList.add('job-outcomes-cleared');
   document.querySelectorAll('.status-card').forEach(card=>{
     card.classList.remove('status-card-success','status-card-warning','status-card-failed');
     card.style.background='#fff';
     card.style.borderColor='var(--line)';
   });
 });
 const testForm=document.querySelector('.test-notification-form');
 if(testForm)testForm.addEventListener('submit',async e=>{
   e.preventDefault();
   const button=testForm.querySelector('button');
   const csrf=testForm.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
   const original=button.textContent;
   button.disabled=true;button.textContent='Sending…';
   try{
     const response=await fetch('/job-status/test-scoring-notification',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({_token:csrf}).toString()});
     const data=await response.json();
     if(!response.ok||!data.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Could not send test notification.');
     box.hidden=false;box.className='job-live-results ok';boxText.textContent=data.message;saveBox();
   }catch(err){
     box.hidden=false;box.className='job-live-results error';boxText.textContent='Test notification failed: '+err.message;saveBox();
   }finally{button.disabled=false;button.textContent=original;}
 });
 const headings={projections:'Regenerating projected FPts for the top 1,000 players...',players:'Getting available players in Fantrax...',goalies:'Getting goalie information from Daily Faceoff...',lines:'Getting Lines information from Daily Faceoff...',odds:'Getting NHL moneyline odds...',teams:'Getting current fantasy team rosters from Fantrax...',scores:'Refreshing live daily scores...',standings:'Refreshing current standings from Fantrax...',advisor:'Regenerating lineup advice for all teams...'};
 const colorCard=(job,status)=>{
   const card=document.querySelector('.status-card[data-job-key="'+job+'"]');
   if(!card)return;
   card.classList.remove('status-card-success','status-card-warning','status-card-failed');
   card.style.background='';
   card.style.borderColor='';
   if(status==='success')card.classList.add('status-card-success');
   else if(status==='warning')card.classList.add('status-card-warning');
   else if(status==='failed')card.classList.add('status-card-failed');
 };
 const parse=(job,output)=>{
   if(job==='players'){
     const counts=[...output.matchAll(/\d{4}-\d{2}-\d{2}:\s*(\d+) Fantrax players refreshed/g)].map(m=>Number(m[1]));
     return (counts.reduce((a,b)=>a+b,0)||0)+' records updated';
   }
   if(job==='goalies'){
     const rows=[...output.matchAll(/(\d{4}-\d{2}-\d{2}):\s*(\d+) DFO goalies refreshed/g)];
     return rows.map(m=>m[2]+' Goalies for '+m[1]).join('\n') || output;
   }
   if(job==='lines'){
     const teams=[...output.matchAll(/^([A-Z]{2,3}): updated$/gm)].map(m=>m[1]);
     return teams.length+' Lines updated'+(teams.length?' ('+teams.join(', ')+')':'');
   }
   if(job==='odds'){
     const rows=[...output.matchAll(/(\d{4}-\d{2}-\d{2}):\s*(\d+) NHL team odds refreshed/g)];
     return rows.map(m=>m[2]+' team odds for '+m[1]).join('\n') || output;
   }
   if(job==='teams'){
     const rows=[...output.matchAll(/(\d{4}-\d{2}-\d{2}):\s*(\d+) roster players refreshed across (\d+) fantasy teams/g)];
     return rows.map(m=>m[2]+' roster players across '+m[3]+' teams for '+m[1]).join('\n') || output;
   }
   if(job==='scores'){
     const rows=[...output.matchAll(/(\d{4}-\d{2}-\d{2}):\s*(\d+) Fantrax daily scores refreshed/g)];
     return rows.map(m=>m[2]+' daily scores for '+m[1]).join('\n') || output;
   }
   if(job==='standings'){
     const match=output.match(/(\d+) Fantrax standings rows refreshed/);
     return match?match[1]+' standings teams updated':output;
   }
   if(job==='advisor'){
     const match=output.match(/(\d+) lineup advisor rows refreshed/);
     return match?match[1]+' teams analyzed':output;
   }
   return output;
 };
 const runOne=async(job,lines,csrf)=>{
   lines.push(headings[job]);boxText.textContent=lines.join('\n');saveBox();
   try{
     const response=await fetch('/job-status/run/'+job,{
       method:'POST',
       credentials:'same-origin',
       headers:{
         'Accept':'application/json',
         'X-Requested-With':'XMLHttpRequest',
         'X-CSRF-TOKEN':csrf,
         'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'
       },
       body:new URLSearchParams({_token:csrf,return_to:'job-status'}).toString()
     });
     let data;try{data=await response.json();}catch(e){throw new Error('The '+job+' job did not return a valid response.');}
     const detail=data.details?.[job]||{};
     if(detail.failed){
       lines.push('Failed: '+(detail.output||data.message||'Job failed.'),'');
       boxText.textContent=lines.join('\n');saveBox();
       colorCard(job,'failed');
       return {status:'failed'};
     }
     if(detail.warning){
       lines.push('Warning: '+parse(job,detail.output||''),'');
       boxText.textContent=lines.join('\n');saveBox();
       colorCard(job,'warning');
       return {status:'warning'};
     }
     lines.push(parse(job,detail.output||''),'');boxText.textContent=lines.join('\n');saveBox();
     colorCard(job,'success');
     return {status:'success'};
   }catch(err){
     lines.push('Failed: '+err.message,'');
     boxText.textContent=lines.join('\n');saveBox();
     colorCard(job,'failed');
     return {status:'failed'};
   }
 };
 document.querySelectorAll('.job-ajax-form').forEach(form=>form.addEventListener('submit',async e=>{
   e.preventDefault();const requested=form.dataset.job;const jobs=requested==='all'?['projections','players','goalies','lines','odds','teams','scores','standings','advisor']:[requested];const button=form.querySelector('button');const original=button.textContent;const lines=[];const csrf=form.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
   if(requested==='all'){
     sessionStorage.removeItem(clearedKey);
     statusRoot?.classList.remove('job-outcomes-cleared');
     document.querySelectorAll('.status-card').forEach(card=>{card.style.background='';card.style.borderColor='';});
   }else{
     sessionStorage.setItem(clearedKey,'1');
     statusRoot?.classList.add('job-outcomes-cleared');
     document.querySelectorAll('.status-card').forEach(card=>{
       if(card.dataset.jobKey!==requested){
         card.classList.remove('status-card-success','status-card-warning','status-card-failed');
         card.style.background='#fff';
         card.style.borderColor='var(--line)';
       }
     });
     document.querySelectorAll('.status-card').forEach(card=>card.classList.remove('job-run-active'));
     const activeCard=document.querySelector('.status-card[data-job-key="'+requested+'"]');
     if(activeCard){
       activeCard.classList.add('job-run-active');
       activeCard.style.background='';
       activeCard.style.borderColor='';
     }
   }
   document.querySelectorAll('.job-ajax-form button').forEach(b=>b.disabled=true);button.textContent=requested==='all'?'Refreshing…':'Running…';box.hidden=false;box.className='job-live-results';boxText.textContent='';saveBox();
   const outcomes=[];
   for(const job of jobs)outcomes.push(await runOne(job,lines,csrf));
   const failed=outcomes.filter(x=>x.status==='failed').length;
   const warnings=outcomes.filter(x=>x.status==='warning').length;
   box.classList.add(failed?'error':(warnings?'warning':'ok'));saveBox();
   if(requested==='all'){
     lines.push(failed?('Finished all '+jobs.length+' jobs: '+failed+' failed'+(warnings?', '+warnings+' warning'+(warnings===1?'':'s'):'')+'.'):(warnings?('Finished all '+jobs.length+' jobs with '+warnings+' warning'+(warnings===1?'':'s')+'.'):'All '+jobs.length+' jobs completed.'));
   }else{
     lines.push(failed?'Job failed.':(warnings?'Job completed with a warning.':'Job completed.'));
   }
   boxText.textContent=lines.join('\n');saveBox();
   document.querySelectorAll('.job-ajax-form button').forEach(b=>b.disabled=false);button.textContent=original;
  }));
});
</script>
@endsection

