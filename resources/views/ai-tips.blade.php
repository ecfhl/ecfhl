@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell">
    <div class="eyebrow">Daily pickup watch</div>
    <h1>AI Tips</h1>
    <p>Available ECFHL players with a game on {{ $selectedDate->format('F j, Y') }}.</p>
</div></div>
<div class="shell ai-tips">
    <form class="toolbar tips-toolbar" method="get" action="/ai-tips">
        <label for="tips-date">Game date</label>
        <input class="control" id="tips-date" name="date" type="date" value="{{ $date }}" required>
        <button class="button primary" type="submit">View tips</button>
        <a class="filter-button" href="/ai-tips?date={{ $today }}">Today</a>
        <a class="filter-button" href="/ai-tips?date={{ $tomorrow }}">Tomorrow</a>
    </form>
    @if(!$snapshot)
        <div class="card"><h2>No tips published for this date</h2><p class="subtle">Availability and starting goalies have not been checked for {{ $selectedDate->format('F j') }} yet.</p>
        @if($availableDates)<div class="filter-group"><span>Published dates</span>@foreach($availableDates as $publishedDate)<a class="filter-button" href="/ai-tips?date={{ $publishedDate }}">{{ $publishedDate }}</a>@endforeach</div>@endif
        </div>
    @else
        <div class="card tips-note">
            <strong>Checked {{ \Carbon\CarbonImmutable::parse($snapshot['checked_at'])->setTimezone('America/Halifax')->format('M j, Y · g:i a T') }}</strong>
            <p>Published snapshot. Availability and starting assignments can change; check Fantrax before claiming. W = waivers; FA = free agent. @ = away, no @ = home.</p>
        </div>
        <nav class="filter-group tips-jumps" aria-label="Player sections">
            <a class="filter-button" href="#goalies">Goalies · {{ count($groups['G']) }}</a>
            <a class="filter-button" href="#forwards">Forwards · {{ count($groups['F']) }}</a>
            <a class="filter-button" href="#defensemen">Defensemen · {{ count($groups['D']) }}</a>
        </nav>
        @foreach(['G'=>['goalies','Available goalies','All available goalies listed by Daily Faceoff for this date. Unconfirmed does not guarantee a start.'], 'F'=>['forwards','Top 10 available forwards','Ranked by projected season fantasy points in ECFHL scoring.'], 'D'=>['defensemen','Top 5 available defensemen','Ranked by projected season fantasy points in ECFHL scoring.']] as $position=>$section)
        <section class="tips-section" id="{{ $section[0] }}">
            <div class="section-title"><div><h2>{{ $section[1] }}</h2><p class="subtle">{{ $section[2] }}</p></div></div>
            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table">
                <thead><tr>@if($position!=='G')<th scope="col">#</th>@endif<th scope="col">{{ $position==='G'?'Goalie':'Player' }}</th><th scope="col">Opponent</th><th scope="col">Status</th><th scope="col">{{ $position==='G'?'Starting status':'Proj. season FPts' }}</th></tr></thead>
                <tbody>@forelse($groups[$position] as $player)<tr>
                    @if($position!=='G')<td>{{ $loop->iteration }}</td>@endif
                    <td><strong>{{ $player['name'] }} ({{ $player['team'] }})</strong></td>
                    <td>{{ $player['opponent'] }}</td>
                    <td><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    <td>@if($position==='G')<span class="pill">{{ $player['starting_status'] }}</span>@else{{ number_format($player['projected_points'], 0) }}@endif</td>
                </tr>@empty<tr><td colspan="{{ $position==='G'?4:5 }}" class="empty">No qualifying available {{ strtolower($section[1])==='available goalies'?'goalies':'players' }} in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
        </section>
        @endforeach
        <p class="subtle tips-method">Skaters are on teams scheduled to play; individual lineup spots are not confirmed. Projections cover the full season, not a single game.</p>
        <div class="filter-group tips-sources"><span>Sources</span><a class="filter-button" href="https://www.dailyfaceoff.com/starting-goalies/{{ $date }}" target="_blank" rel="noopener noreferrer">Daily Faceoff ↗</a><a class="filter-button" href="{{ $snapshot['fantrax_url'] }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" width="16" height="16" alt="">&nbsp; Fantrax ↗</a></div>
    @endif
</div>
<style>
.tips-toolbar{align-items:center}.tips-toolbar label{font-weight:700;font-size:13px}.tips-note{border-left:4px solid var(--accent);padding:16px 20px}.tips-note p{margin:5px 0 0;color:var(--muted);font-size:13px}.tips-jumps{margin:22px 0}.tips-section{margin:28px 0;scroll-margin-top:95px}.tips-section .section-title p{margin:5px 0 0;font-size:13px}.tips-waiver{background:var(--accent-soft);border-color:var(--accent)}.tips-fa{color:var(--success);font-weight:700}.tips-table td:first-child{white-space:normal}.tips-method{font-size:13px}.tips-sources{margin-top:18px}.tips-table .pill{white-space:nowrap}.ai-tips .table-scroll{overflow-x:auto}@media(max-width:520px){.tips-table th,.tips-table td{padding:10px 8px;font-size:12px}.tips-table .pill{padding:4px 6px;font-size:11px}.tips-section h2{font-size:21px}}@media(min-width:851px) and (max-width:1100px){.main-nav a{padding:8px 5px;font-size:12px}.nav-wrap{gap:8px}}
</style>
@endsection
