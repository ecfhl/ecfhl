@extends('layouts.app')
@section('content')
@php
    $fantraxLeagueId = '092zcn40molvao69';
    $fantraxPlayerUrl = function ($player) use ($fantraxLeagueId) {
        return 'https://www.fantrax.com/fantasy/league/'.$fantraxLeagueId.'/players;searchName='.rawurlencode($player['name']).';positionOrGroup=ALL;';
    };
    $ppLines = \Illuminate\Support\Facades\DB::table('active_pp_lines')
        ->select('team', 'player_name', 'pp_unit')
        ->get()
        ->keyBy(fn($row) => strtoupper(trim($row->team)).'|'.mb_strtolower(trim($row->player_name)));
    $ppUnit = function ($player) use ($ppLines) {
        $key = strtoupper(trim($player['team'] ?? '')).'|'.mb_strtolower(trim($player['name'] ?? ''));
        return isset($ppLines[$key]) ? (int)$ppLines[$key]->pp_unit : null;
    };
@endphp
<div class="page-head"><div class="shell">
    <div class="eyebrow">Daily pickup watch</div>
    <h1>AI Tips</h1>
    <p>Available ECFHL players with a game on {{ $selectedDate->format('F j, Y') }}.</p>
</div></div>
<div class="shell ai-tips">
    <div class="toolbar tips-toolbar" role="group" aria-label="Game date">
        <a class="button {{ $date === $today ? 'primary' : 'tips-date-inactive' }}" href="/ai-tips?date={{ $today }}">Today</a>
        <a class="button {{ $date === $tomorrow ? 'primary' : 'tips-date-inactive' }}" href="/ai-tips?date={{ $tomorrow }}">Tomorrow</a>
    </div>
    @if(!$snapshot)
        <div class="card"><h2>No tips published for this date</h2><p class="subtle">Availability and starting goalies have not been checked for {{ $selectedDate->format('F j') }} yet.</p></div>
    @else
        <section class="tips-section" id="goalies">
            <div class="section-title"><div><h2>Top 10 available goalies</h2><p class="subtle">Available goalies listed by Daily Faceoff are included first. Remaining spots are filled with the highest projected available goalies playing that day.</p></div></div>
            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table tips-goalie-table">
                <thead><tr><th scope="col">Goalie</th><th scope="col">Opponent</th><th scope="col">Status</th><th scope="col">Starting status</th><th scope="col">Proj. season FPts</th><th scope="col">Fantrax</th></tr></thead>
                <tbody>@forelse($groups['G'] as $player)<tr>
                    @php($isAway = str_starts_with(trim($player['opponent'] ?? ''), '@'))
                    @php($mobileOpponent = $isAway ? $player['opponent'] : 'vs '.trim($player['opponent'] ?? ''))
                    <td data-label="Goalie" data-opponent="{{ $mobileOpponent }}" class="tips-player-cell {{ $isAway ? 'tips-away' : 'tips-home' }}"><strong>@if(!empty($player['injury_status']) && preg_match('/IR/i', $player['injury_status']))<span class="pill tips-ir">IR</span>@endif<span class="tips-player-name">{{ $player['name'] }} ({{ $player['team'] }})</span></strong></td>
                    <td data-label="Opponent">{{ $player['opponent'] }}</td>
                    <td data-label="Status"><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    <td data-label="Starting">@if(!empty($player['starting_status']))@php($startingClass = match(strtolower($player['starting_status'])) {'confirmed' => 'tips-start-confirmed', 'probable' => 'tips-start-probable', default => 'tips-start-unconfirmed'})<span class="pill {{ $startingClass }}">{{ $player['starting_status'] }}</span>@else<span class="pill tips-start-na">NA</span>@endif</td>
                    <td data-label="Proj. FPts">@if(array_key_exists('projected_points', $player))<span class="tips-proj-value">{{ number_format($player['projected_points'], 0) }}</span>@else<span class="tips-proj-value subtle">—</span>@endif</td>
                    <td data-label="Fantrax"><a class="tips-add-button" href="{{ $fantraxPlayerUrl($player) }}" target="_blank" rel="noopener noreferrer">+ Add</a></td>
                </tr>@empty<tr><td colspan="6" class="empty">No qualifying available goalies in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
        </section>

        @foreach(['F'=>['forwards','Top 10 available forwards','Ranked by projected season fantasy points in ECFHL scoring.'], 'D'=>['defensemen','Top 5 available defensemen','Ranked by projected season fantasy points in ECFHL scoring.']] as $position=>$section)
        <section class="tips-section" id="{{ $section[0] }}">
            <div class="section-title"><div><h2>{{ $section[1] }}</h2><p class="subtle">{{ $section[2] }}</p></div></div>
            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table tips-skater-table">
                <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Opponent</th><th scope="col">Status</th><th scope="col">Proj. season FPts</th><th scope="col">Fantrax</th></tr></thead>
                <tbody>@forelse($groups[$position] as $player)<tr>
                    @php($isAway = str_starts_with(trim($player['opponent'] ?? ''), '@'))
                    @php($mobileOpponent = $isAway ? $player['opponent'] : 'vs '.trim($player['opponent'] ?? ''))
                    @php($playerPpUnit = $ppUnit($player))
                    <td data-label="#">{{ $loop->iteration }}</td>
                    <td data-label="Player" data-opponent="{{ $mobileOpponent }}" class="tips-player-cell {{ $isAway ? 'tips-away' : 'tips-home' }}"><strong>@if(!empty($player['injury_status']) && preg_match('/IR/i', $player['injury_status']))<span class="pill tips-ir">IR</span>@endif<span class="tips-player-with-pp"><span class="tips-player-name">{{ $player['name'] }} ({{ $player['team'] }})</span>@if($playerPpUnit === 1)<span class="pill tips-pp tips-pp1">PP1</span>@elseif($playerPpUnit === 2)<span class="pill tips-pp tips-pp2">PP2</span>@endif</span></strong></td>
                    <td data-label="Opponent">{{ $player['opponent'] }}</td>
                    <td data-label="Status"><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    <td data-label="Proj. FPts"><span class="tips-proj-value">{{ number_format($player['projected_points'], 0) }}</span></td>
                    <td data-label="Fantrax"><a class="tips-add-button" href="{{ $fantraxPlayerUrl($player) }}" target="_blank" rel="noopener noreferrer">+ Add</a></td>
                </tr>@empty<tr><td colspan="6" class="empty">No qualifying available players in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
        </section>
        @endforeach
        <p class="subtle tips-method">Players are on teams scheduled to play; individual lineup spots are not confirmed unless a goalie starting status is shown. Projections cover the full season, not a single game.</p>
        <div class="filter-group tips-sources"><span>Sources</span><a class="filter-button" href="https://www.dailyfaceoff.com/starting-goalies/{{ $date }}" target="_blank" rel="noopener noreferrer">Daily Faceoff ↗</a><a class="filter-button" href="{{ $snapshot['fantrax_url'] }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" width="16" height="16" alt="">&nbsp; Fantrax ↗</a></div>
        <div class="tips-updated subtle">
            Updated {{ \Carbon\CarbonImmutable::parse($snapshot['checked_at'])->setTimezone('America/Halifax')->format('M j, Y · g:i a T') }}
            @if(($snapshot['refresh_status'] ?? 'success') === 'failed')
                <span class="tips-refresh-failed" title="{{ $snapshot['refresh_error'] ?? 'Refresh failed' }}">Refresh failed{{ !empty($snapshot['last_attempt_at']) ? ' · '.\Carbon\CarbonImmutable::parse($snapshot['last_attempt_at'])->setTimezone('America/Halifax')->format('g:i a T') : '' }}</span>
            @endif
        </div>
    @endif
</div>
<style>
.tips-toolbar{align-items:center;margin-bottom:18px}.tips-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.tips-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.tips-section{margin:28px 0;scroll-margin-top:95px}.tips-section .section-title p{margin:5px 0 0;font-size:13px}.tips-waiver{background:var(--accent-soft);border-color:var(--accent)}.tips-fa{color:var(--success);font-weight:700}.tips-ir{background:#dc2626;color:#fff;border-color:#dc2626;font-weight:800;margin-right:6px}.tips-player-with-pp{display:inline-flex;align-items:center;gap:6px;min-width:0}.tips-pp{flex:0 0 auto;font-weight:800}.tips-pp1{background:#7c3aed;color:#fff;border-color:#6d28d9}.tips-pp2{background:#ddd6fe;color:#4c1d95;border-color:#a78bfa}.tips-start-confirmed{background:#16a34a;color:#fff;border-color:#15803d;font-weight:800}.tips-start-probable{background:#facc15;color:#422006;border-color:#eab308;font-weight:800}.tips-start-unconfirmed,.tips-start-na{background:#e5e7eb;color:#374151;border-color:#d1d5db;font-weight:700}.tips-add-button{display:inline-flex;align-items:center;justify-content:center;background:#2563eb;color:#fff!important;border:1px solid #1d4ed8;border-radius:8px;padding:6px 12px;font-weight:800;font-size:12px;text-decoration:none;white-space:nowrap}.tips-add-button:hover{background:#1d4ed8;text-decoration:none}.tips-table td:first-child{white-space:normal}.tips-method{font-size:13px}.tips-sources{margin-top:18px}.tips-updated{margin-top:24px;padding:14px 0 4px;border-top:1px solid var(--line);font-size:12px;text-align:right}.tips-refresh-failed{display:inline-block;margin-left:8px;padding:3px 7px;border-radius:999px;background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;font-weight:800}.tips-table .pill{white-space:nowrap}.ai-tips .table-scroll{overflow-x:auto}
@media(max-width:600px){
.ai-tips .table-card{background:transparent;border:0;box-shadow:none;overflow:visible}.ai-tips .table-scroll{overflow:visible}.tips-table,.tips-table tbody{display:block;width:100%}.tips-table thead{display:none}.tips-table tr{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-rows:auto auto;column-gap:10px;row-gap:8px;align-items:center;background:var(--surface);border:1px solid var(--line);border-radius:14px;margin-bottom:12px;padding:14px;box-shadow:0 2px 8px rgba(15,23,42,.05);overflow:hidden;position:relative}.tips-table td{border:0!important;padding:0!important;font-size:13px;white-space:normal}.tips-table td::before{display:none}.tips-table td[data-label="Goalie"],.tips-table td[data-label="Player"]{grid-column:1;grid-row:1;min-width:0;text-align:left;background:transparent!important;border:0!important;padding-right:72px!important}.tips-player-cell strong{display:flex;align-items:center;min-width:0;font-size:16px;line-height:1.2}.tips-player-name{min-width:0}.tips-player-with-pp{display:inline-flex;align-items:center;min-width:0}.tips-skater-table .tips-player-with-pp .tips-pp{position:absolute;left:14px;bottom:19px}.tips-player-cell::after{content:attr(data-opponent);display:block;margin-top:5px;font-size:12px;font-weight:800;line-height:1}.tips-player-cell.tips-away::after{color:#a16207}.tips-player-cell.tips-home::after{color:#15803d}.tips-table td[data-label="Opponent"]{display:none}.tips-table td[data-label="Proj. FPts"]{position:absolute;right:14px;top:14px;text-align:right;min-width:54px;padding-left:10px!important;border-left:1px solid var(--line)!important}.tips-table td[data-label="Proj. FPts"]::before{display:block;content:"Proj. Pts";color:var(--muted);font-size:10px;font-weight:700;white-space:nowrap;margin-bottom:1px}.tips-proj-value{display:block;font-size:22px;line-height:1;font-weight:800;color:var(--text)}.tips-table td[data-label="Fantrax"]{position:absolute;right:14px;bottom:12px;margin:0}.tips-table td[data-label="Status"]{position:absolute;right:100px;bottom:15px;margin:0}.tips-goalie-table td[data-label="Starting"]{grid-column:1;grid-row:2;justify-self:start;align-self:end;margin-top:18px}.tips-skater-table td[data-label="#"]{display:none}.tips-skater-table tr{min-height:112px;padding-bottom:52px}.tips-goalie-table tr{min-height:118px;padding-bottom:52px}.tips-table .pill{padding:5px 9px;font-size:11px}.tips-table td[data-label="Status"] .tips-waiver{background:#fff7d6;color:#713f12;border-color:#eab308;font-weight:800}.tips-table td[data-label="Status"] .tips-fa{display:inline-flex;background:#e5e7eb;color:#374151;border:1px solid #d1d5db;font-weight:800}.tips-ir{flex:0 0 auto;margin:0 6px 0 0;background:#dc2626!important;color:#fff!important;border-color:#dc2626!important}.tips-pp{padding:4px 7px!important;font-size:10px!important}.tips-add-button{min-width:72px;padding:8px 13px;background:#2563eb;border-color:#1d4ed8;font-size:12px}.tips-section h2{font-size:21px}.tips-updated{text-align:left}.tips-refresh-failed{margin:6px 0 0;display:table}.tips-sources{gap:7px}.tips-sources>span{width:100%}
}
@media(min-width:851px) and (max-width:1100px){.main-nav a{padding:8px 5px;font-size:12px}.nav-wrap{gap:8px}}
</style>
@endsection
