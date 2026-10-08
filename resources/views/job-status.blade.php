@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Data collectors</div><h1>Collector Status</h1><p>Live activity and the latest result for each collector.</p></div></div>
<div class="shell job-status">
    <div class="collector-actions">
        <a class="button" href="/players">← Players</a>
        <form class="test-notification-form" method="POST" action="/job-status/test-scoring-notification">@csrf<button class="button">Send Test Notification</button></form>
        <form class="job-ajax-form" data-job="all" method="POST" action="/job-status/run/all">@csrf<button class="button primary">Refresh All</button></form>
    </div>
    <p class="collector-ticker" role="status" aria-live="polite">Ready · Times shown in Atlantic time</p>
    <div class="collector-grid">
        @foreach($jobs as $job)
        @php($run = $statuses[$job['key']] ?? ['status'=>'idle','message'=>'Ready','completed'=>0,'total'=>null,'details'=>'','updated_at'=>null])
        <section class="card collector-card" id="collector-{{ $job['key'] }}" data-job-key="{{ $job['key'] }}" data-status="{{ $run['status'] }}">
            <div class="collector-head"><h2>{{ $job['name'] }}</h2><span class="collector-state">{{ ucfirst($run['status']) }}</span></div>
            <p class="collector-cadence">{{ $job['schedule'] }}</p>
            <p class="collector-message">{{ $run['message'] }}</p>
            <div class="collector-progress">
                <progress aria-label="{{ $job['name'] }} progress" max="{{ $run['total'] ?? 1 }}" @if($run['status'] !== 'running' || $run['total']) value="{{ $run['status']==='success' ? ($run['total'] ?? 1) : $run['completed'] }}" @endif></progress>
                <span class="collector-units">{{ $run['total'] ? $run['completed'].' / '.$run['total'].' units checked' : ($run['status']==='running'?'Working…':ucfirst($run['status'])) }}</span>
            </div>
            <dl class="collector-meta"><div><dt>Last run</dt><dd class="collector-updated">{{ $run['updated_at'] ?? 'Never' }}</dd></div>@if($job['records'] !== null)<div><dt>Stored records</dt><dd>{{ number_format($job['records']) }}</dd></div>@endif</dl>
            <details class="collector-details"><summary>Details</summary><p>{{ $job['description'] }}</p><pre>{{ $run['details'] }}</pre></details>
            <form class="job-ajax-form" data-job="{{ $job['key'] }}" method="POST" action="/job-status/run/{{ $job['key'] }}">@csrf<button class="button primary">Run Now</button></form>
        </section>
        @endforeach
    </div>
    <p class="subtle">Fantasy dates change at Pacific midnight. Active hours are 8 a.m.–10 p.m. Pacific, plus pregame and live windows. Progress counts checked dates or teams, including unchanged data; failures remain visible in Details.</p>
</div>
@push('styles')
<link rel="stylesheet" href="/collector-status.css?v={{ hash_file('sha256', base_path('public/collector-status.css')) }}">
@endpush
<script src="/collector-status.js?v={{ hash_file('sha256', base_path('public/collector-status.js')) }}" defer></script>
@endsection
