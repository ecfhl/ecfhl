@extends('layouts.app')
@section('title', 'Notification Tests · ECFHL')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Notification Tests</h1><p>Send each notification type to your current device only. No tests are sent to other owners or added to chat history.</p></div></div>
<div class="shell">
@if(session('notice'))<div class="card" style="margin:16px 0">{{ session('notice') }}</div>@endif
<div class="admin-notification-grid">
@foreach([
 ['league-message','League chat messages','Send a sample League chat notification to your current device only.'],
 ['private-message','Private messages','Send a sample private-message notification to your current device only.'],
 ['team-score','My team scores','Skater scoring alert with G, A, PPG, SHG and GWG.'],
 ['team-goalie-score','My team goalie scores','Goalie scoring alert with W, L, OL, SO, G and A.'],
 ['opponent-score','My opponent scores','Scoring alert from the current opponent.'],
 ['own-goalie','Goalies on my team','Starting-status change for a rostered goalie.'],
 ['all-goalie','All goalies','Global starting-status change.'],
 ['available-today','Available goalies · Today','Available-goalie status change for today.'],
 ['available-tomorrow','Available goalies · Tomorrow','Available-goalie status change for tomorrow.'],
 ['watched-goalie','Specific watched goalie','Status change for an individually watched available goalie.'],
] as [$type,$title,$description])
<form class="card admin-notification-card" method="post" action="/admin/notifications/test">
 @csrf
 <input type="hidden" name="type" value="{{ $type }}">
 <div><h2>{{ $title }}</h2><p>{{ $description }}</p></div>
 <button class="button primary" type="submit">Send test</button>
</form>
@endforeach
</div>
</div>
@push('styles')
<style>
.admin-notification-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:18px 0 32px}.admin-notification-card{display:flex;align-items:center;gap:16px;padding:18px}.admin-notification-card>div{min-width:0;flex:1}.admin-notification-card h2{font-size:17px;margin:0 0 5px}.admin-notification-card p{font-size:13px;line-height:1.4;color:var(--muted);margin:0}.admin-notification-card .button{white-space:nowrap}@media(max-width:700px){.admin-notification-grid{grid-template-columns:1fr}.admin-notification-card{align-items:flex-start;flex-direction:column}.admin-notification-card .button{width:100%}}
</style>
@endpush
@endsection
