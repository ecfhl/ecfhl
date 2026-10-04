@extends('layouts.app')
@section('title', 'Administration · ECFHL')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Admin</h1><p>Choose an area to manage.</p></div></div>
<div class="shell admin-menu-grid">
  @foreach([
    ['/admin/projections', '📊', 'Projection Weights', 'Adjust your MyProj formula and preview the top players.'],
    ['/job-status', '🔄', 'Collector Status', 'Check data refreshes and run collectors.'],
    ['/admin/advisors', '💬', 'Advisors', 'Manage lineup advisors and their profiles.'],
    ['/admin/team-images', '🖼️', 'Team Images', 'Upload and update league team logos.'],
  ] as [$url, $icon, $title, $description])
    <a class="card admin-menu-card" href="{{ $url }}"><span class="admin-menu-icon" aria-hidden="true">{{ $icon }}</span><div><h2>{{ $title }}</h2><p>{{ $description }}</p></div><span class="admin-menu-arrow" aria-hidden="true">→</span></a>
  @endforeach
</div>
<style>
.admin-menu-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:18px;padding-bottom:32px}.admin-menu-card{display:flex;align-items:center;gap:16px;padding:22px;color:var(--text);text-decoration:none!important;border:1px solid var(--line)}.admin-menu-card:hover,.admin-menu-card:focus-visible{border-color:var(--accent);background:var(--panel)}.admin-menu-icon{font-size:32px}.admin-menu-card h2{font-size:20px;margin:0 0 7px}.admin-menu-card p{font-size:14px;line-height:1.5;color:var(--muted);margin:0}.admin-menu-arrow{font-size:23px;margin-left:auto}@media(max-width:600px){.admin-menu-grid{grid-template-columns:1fr;gap:10px}.admin-menu-card{padding:18px}.admin-menu-card h2{font-size:18px}}
</style>
@endsection
