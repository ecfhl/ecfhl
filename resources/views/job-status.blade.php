@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Data collectors</div><h1>Job Status</h1><p>Current status of the automated data used by AI Tips.</p></div></div>
<div class="shell job-status">
    <div class="status-actions"><a class="button status-back" href="/ai-tips">← Back to AI Tips</a></div>
    <div class="status-grid">
        @foreach($jobs as $job)
        @php
            $jobKey = str_contains($job['name'], 'Fantrax') ? 'players' : (str_contains($job['name'], 'Goalies') ? 'goalies' : 'lines');
            $displaySchedule = $jobKey === 'players' ? 'Every hour at :30' : $job['schedule'];
        @endphp
        <section class="card status-card">
            <div class="status-head"><div><h2>{{ $job['name'] }}</h2><p class="subtle">{{ $displaySchedule }}</p></div><span class="pill {{ $job['state']==='Current'?'status-ok':($job['state']==='No data'?'status-empty':'status-stale') }}">{{ $job['state'] }}</span></div>
            <dl>
                <div><dt>Last data update</dt><dd>{{ $job['last_update'] ?? 'Never' }}</dd></div>
                <div><dt>Records</dt><dd>{{ number_format($job['records']) }}</dd></div>
                <div><dt>Next scheduled run</dt><dd>{{ $job['next_run'] }}</dd></div>
            </dl>
            <p class="subtle status-note">{{ $job['description'] }}</p>
            <form method="POST" action="/job-status/run/{{ $jobKey }}" class="run-form" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Running…';">
                @csrf
                <button type="submit" class="button primary run-now">Run Now</button>
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
.status-actions{margin:20px 0 0}.status-back{text-decoration:none}.status-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin:18px 0 24px}.status-card{padding:20px}.status-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.status-head h2{margin:0;font-size:19px}.status-head p{margin:4px 0 0}.status-card dl{margin:20px 0 0}.status-card dl div{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-top:1px solid var(--line)}.status-card dt{color:var(--muted);font-size:13px}.status-card dd{margin:0;text-align:right;font-weight:800}.status-ok{background:#dcfce7;color:#166534;border-color:#86efac}.status-stale{background:#fef3c7;color:#92400e;border-color:#fcd34d}.status-empty{background:#e5e7eb;color:#4b5563;border-color:#d1d5db}.status-note{font-size:12px;margin:14px 0 0}.status-footer{font-size:12px;margin-bottom:28px}.run-form{margin-top:16px}.run-now{width:100%;cursor:pointer}.run-now:disabled{opacity:.65;cursor:wait}.job-results{margin:0 0 22px}.job-results h2{font-size:17px;margin:0 0 10px}.job-message{padding:12px 14px;border-radius:10px;font-weight:700;white-space:pre-wrap}.job-message+.job-message{margin-top:10px}.job-message-ok{background:#dcfce7;color:#166534;border:1px solid #86efac}.job-message-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}@media(max-width:800px){.status-grid{grid-template-columns:1fr}.status-card{padding:16px}}
</style>
@endsection
