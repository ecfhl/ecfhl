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
            <a class="filter-button" href="#other-goalies">Other Goalies · {{ count($groups['GO'] ?? []) }}</a>
            <a class="filter-button" href="#forwards">Forwards · {{ count($groups['F']) }}</a>
            <a class="filter-button" href="#defensemen">Defensemen · {{ count($groups['D']) }}</a>
        </nav>

        <section class="tips-section" id="goalies">
            <div class="section-title"><div><h2>Available goalies</h2><p class="subtle">Available goalies listed by Daily Faceoff for this date. Unconfirmed does not guarantee a start.</p></div></div>
            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table">
                <thead><tr><th scope="col">Goalie</th><th scope="col">Opponent</th><th scope="col">Status</th><th scope="col">Starting status</th></tr></thead>
                <tbody>@forelse($groups['G'] as $player)<tr>
                    <td><strong>{{ $player['name'] }} ({{ $player['team'] }})</strong>@if(!empty($player['injury_status']) && preg_match('/IR/i', $player['injury_status'])) <span class="pill tips-ir">IR</span>@endif</td>
                    <td>{{ $player['opponent'] }}</td>
                    <td><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    <td><span class="pill">{{ $player['starting_status'] }}</span></td>
                </tr>@empty<tr><td colspan="4" class="empty">No qualifying available goalies in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
        </section>

        <section class="tips-section" id="other-goalies">
            <div class="section-title"><div><h2>Top 10 other available goalies</h2><p class="subtle">Other available goalies playing this date, ranked by projected season fantasy points in ECFHL scoring.</p></div></div>
            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table">
                <thead><tr><th scope="col">#</th><th scope="col">Goalie</th><th scope="col">Opponent</th><th scope="col">Status</th><th scope="col">Proj. season FPts</th></tr></thead>
                <tbody>@forelse(($groups['GO'] ?? []) as $player)<tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $player['name'] }} ({{ $player['team'] }})</strong>@if(!empty($player['injury_status']) && preg_match('/IR/i', $player['injury_status'])) <span class="pill tips-ir">IR</span>@endif</td>
                    <td>{{ $player['opponent'] }}</td>
                    <td><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    <td>{{ number_format($player['projected_points'], 0) }}</td>
                </tr>@empty<tr><td colspan="5" class="empty">No other available goalies with projections in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
        </section>

        @foreach(['F'=>['forwards','Top 10 available forwards','Ranked by projected season fantasy points in ECFHL scoring.'], 'D'=>['defensemen','Top 5 available defensemen','Ranked by projected season fantasy points in ECFHL scoring.']] as $position=>$section)
        <section class="tips-section" id="{{ $section[0] }}">
            <div class="section-title"><div><h2>{{ $section[1] }}</h2><p class="subtle">{{ $section[2] }}</p></div></div>
            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table">
                <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Opponent</th><th scope="col">Status</th><th scope="col">Proj. season FPts</th></tr></thead>
                <tbody>@forelse($groups[$position] as $player)<tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $player['name'] }} ({{ $player['team'] }})</strong>@if(!empty($player['injury_status']) && preg_match('/IR/i', $player['injury_status'])) <span class="pill tips-ir">IR</span>@endif</td>
                    <td>{{ $player['opponent'] }}</td>
                    <td><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    <td>{{ number_format($player['projected_points'], 0) }}</td>
                </tr>@empty<tr><td colspan="5" class="empty">No qualifying available players in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
        </section>
        @endforeach
        <p class="subtle tips-method">Players are on teams scheduled to play; individual lineup spots are not confirmed unless a goalie starting status is shown. Projections cover the full season, not a single game.</p>
        <div class="filter-group tips-sources"><span>Sources</span><a class="filter-button" href="https://www.dailyfaceoff.com/starting-goalies/{{ $date }}" target="_blank" rel="noopener noreferrer">Daily Faceoff ↗</a><a class="filter-button" href="{{ $snapshot['fantrax_url'] }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" width="16" height="16" alt="">&nbsp; Fantrax ↗</a></div>
    @endif
</div>
<style>
.tips-toolbar{align-items:center}.tips-toolbar label{font-weight:700;font-size:13px}.tips-note{border-left:4px solid var(--accent);padding:16px 20px}.tips-note p{margin:5px 0 0;color:var(--muted);font-size:13px}.tips-jumps{margin:22px 0}.tips-section{margin:28px 0;scroll-margin-top:95px}.tips-section .section-title p{margin:5px 0 0;font-size:13px}.tips-waiver{background:var(--accent-soft);border-color:var(--accent)}.tips-fa{color:var(--success);font-weight:700}.tips-ir{background:#dc2626;color:#fff;border-color:#dc2626;font-weight:800;margin-left:6px}.tips-table td:first-child{white-space:normal}.tips-method{font-size:13px}.tips-sources{margin-top:18px}.tips-table .pill{white-space:nowrap}.ai-tips .table-scroll{overflow-x:auto}@media(max-width:520px){.tips-table th,.tips-table td{padding:10px 8px;font-size:12px}.tips-table .pill{padding:4px 6px;font-size:11px}.tips-section h2{font-size:21px}}@media(min-width:851px) and (max-width:1100px){.main-nav a{padding:8px 5px;font-size:12px}.nav-wrap{gap:8px}}
</style>
@endsection
