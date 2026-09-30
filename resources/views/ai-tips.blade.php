@extends('layouts.app')
@section('content')
@php
    $fantraxLeagueId = '092zcn40molvao69';
    $fantraxPlayerUrl = function ($player) use ($fantraxLeagueId) { return 'https://www.fantrax.com/fantasy/league/'.$fantraxLeagueId.'/players;searchName='.rawurlencode($player['name']).';positionOrGroup=ALL;'; };
    $fantraxListingUrl = 'https://www.fantrax.com/fantasy/league/'.$fantraxLeagueId.'/players;maxResultsPerPage=500;pageNumber=1;seasonOrProjection=PROJECTION_0_31n_SEASON;timeframeTypeCode=PROJECTED_SEASON;datePlaying='.$date;
    $fantraxPool = app(\App\Support\FantraxAvailablePlayers::class);
    $fantraxGoaliesUrl = $fantraxPool->url($selectedDate, 'G').';statusOrTeamFilter=ALL_AVAILABLE;positionOrGroup=POS_201';
    $fantraxForwardsUrl = $fantraxPool->url($selectedDate, 'F').';statusOrTeamFilter=ALL_AVAILABLE;positionOrGroup=POS_207';
    $fantraxDefenseUrl = $fantraxPool->url($selectedDate, 'D').';statusOrTeamFilter=ALL_AVAILABLE;positionOrGroup=POS_202';
    $dfoTeamSlugs = [
        'ANA'=>'anaheim-ducks','BOS'=>'boston-bruins','BUF'=>'buffalo-sabres','CAR'=>'carolina-hurricanes',
        'CBJ'=>'columbus-blue-jackets','CGY'=>'calgary-flames','CHI'=>'chicago-blackhawks','COL'=>'colorado-avalanche',
        'DAL'=>'dallas-stars','DET'=>'detroit-red-wings','EDM'=>'edmonton-oilers','FLA'=>'florida-panthers',
        'LA'=>'los-angeles-kings','LAK'=>'los-angeles-kings','MIN'=>'minnesota-wild','MTL'=>'montreal-canadiens',
        'NJ'=>'new-jersey-devils','NJD'=>'new-jersey-devils','NSH'=>'nashville-predators','NYI'=>'new-york-islanders',
        'NYR'=>'new-york-rangers','OTT'=>'ottawa-senators','PHI'=>'philadelphia-flyers','PIT'=>'pittsburgh-penguins',
        'SEA'=>'seattle-kraken','SJ'=>'san-jose-sharks','SJS'=>'san-jose-sharks','STL'=>'st-louis-blues',
        'TB'=>'tampa-bay-lightning','TBL'=>'tampa-bay-lightning','TOR'=>'toronto-maple-leafs','UTA'=>'utah-mammoth',
        'VAN'=>'vancouver-canucks','VGK'=>'vegas-golden-knights','WPG'=>'winnipeg-jets','WSH'=>'washington-capitals'
    ];
    $dfoLinesUrl = fn($team) => 'https://www.dailyfaceoff.com/teams/'.($dfoTeamSlugs[strtoupper(trim((string)$team))] ?? strtolower(trim((string)$team))).'/line-combinations';
    $ppLines = \Illuminate\Support\Facades\DB::table('active_pp_lines')->select('team','player_name','pp_unit')->get()->keyBy(fn($row)=>strtoupper(trim($row->team)).'|'.mb_strtolower(trim($row->player_name)));
    $evenStrengthLines = \Illuminate\Support\Facades\DB::table('active_line_combinations')->select('team','player_name','position_group','line_number')->get()->keyBy(fn($row)=>strtoupper(trim($row->team)).'|'.mb_strtolower(trim($row->player_name)).'|'.strtoupper(trim($row->position_group)));
    $oddsByTeam = \Illuminate\Support\Facades\DB::table('todays_odds')->whereDate('game_date',$date)->get()->keyBy(fn($row)=>strtoupper(trim($row->team)));
    $ppCollisionPositions = ['VAN|elias pettersson' => 'F'];
    $ppUnit = function($player)use($ppLines,$ppCollisionPositions){
        $key=strtoupper(trim($player['team']??'')).'|'.mb_strtolower(trim($player['name']??''));
        if(isset($ppCollisionPositions[$key]) && strtoupper(trim($player['position']??''))!==$ppCollisionPositions[$key]) return null;
        return isset($ppLines[$key])?(int)$ppLines[$key]->pp_unit:null;
    };
    $lineNumber = function($player)use($evenStrengthLines){
        $position=strtoupper(trim($player['position']??''));
        if(!in_array($position,['F','D','G'],true)) return null;
        $key=strtoupper(trim($player['team']??'')).'|'.mb_strtolower(trim($player['name']??'')).'|'.$position;
        return isset($evenStrengthLines[$key])?(int)$evenStrengthLines[$key]->line_number:null;
    };
    $sortSkaters = function(array &$players) use ($ppUnit, $lineNumber) {
        usort($players, function($a, $b) use ($ppUnit, $lineNumber) {
            $rank = function($player) use ($ppUnit, $lineNumber) {
                $pp = $ppUnit($player);
                $line = $lineNumber($player);
                return [
                    match($pp) {
                        1 => 1,
                        2 => 2,
                        default => 3,
                    },
                    in_array($line, [1, 2, 3, 4], true) ? $line : 99,
                ];
            };
            $aRank = $rank($a);
            $bRank = $rank($b);
            if ($aRank[0] !== $bRank[0]) return $aRank[0] <=> $bRank[0];
            if ($aRank[1] !== $bRank[1]) return $aRank[1] <=> $bRank[1];
            $aPoints = $a['projected_points'] ?? -PHP_FLOAT_MAX;
            $bPoints = $b['projected_points'] ?? -PHP_FLOAT_MAX;
            return ($bPoints <=> $aPoints)
                ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
                ?: strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
    };
    $sortGoalies = function(array &$players) {
        usort($players, function($a, $b) {
            $rank = function($player) {
                if (!empty($player['not_starting'])) return 5;
                $status = strtolower(trim($player['starting_status'] ?? ''));
                return match($status) {
                    'starting', 'confirmed' => 1,
                    'likely', 'probable' => 2,
                    'unconfirmed' => 3,
                    '', 'na', 'n/a' => 4,
                    'not starting', 'not_starting' => 5,
                    default => 4,
                };
            };
            $aRank = $rank($a);
            $bRank = $rank($b);
            if ($aRank !== $bRank) return $aRank <=> $bRank;
            $aPoints = $a['projected_points'] ?? -PHP_FLOAT_MAX;
            $bPoints = $b['projected_points'] ?? -PHP_FLOAT_MAX;
            return ($bPoints <=> $aPoints)
                ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
                ?: strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
    };
    $sortGoalies($groups['G']);
    $sortSkaters($groups['F']);
    $sortSkaters($groups['D']);
    $hasTips = count($groups['G']) + count($groups['F']) + count($groups['D']) > 0;
    $fantraxUpdated = \Illuminate\Support\Facades\DB::table('active_daily_players')->whereDate('game_date',$date)->max('last_update');
    $goaliesUpdated = \Illuminate\Support\Facades\DB::table('active_starting_goalies')->whereDate('game_date',$date)->max('checked_at');
    $linesUpdated = \Illuminate\Support\Facades\DB::table('active_pp_lines')->max('checked_at');
    $oddsUpdated = \Illuminate\Support\Facades\DB::table('todays_odds')->whereDate('game_date',$date)->max('checked_at');
    $updatedValues = array_values(array_filter([$fantraxUpdated,$goaliesUpdated,$linesUpdated,$oddsUpdated]));
    $latestUpdated = $updatedValues ? max($updatedValues) : null;
    $refreshAge = null;
    if ($latestUpdated) {
        $refreshSeconds = max(0, (int) floor(\Carbon\CarbonImmutable::parse($latestUpdated)->diffInSeconds(\Carbon\CarbonImmutable::now())));
        if ($refreshSeconds < 60) {
            $refreshAge = $refreshSeconds.' '.($refreshSeconds === 1 ? 'second' : 'seconds');
        } elseif ($refreshSeconds < 3600) {
            $minutes = intdiv($refreshSeconds, 60);
            $refreshAge = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        } elseif ($refreshSeconds < 86400) {
            $hours = intdiv($refreshSeconds, 3600);
            $minutes = intdiv($refreshSeconds % 3600, 60);
            $refreshAge = $hours.' '.($hours === 1 ? 'hour' : 'hours').' '.$minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        } else {
            $days = intdiv($refreshSeconds, 86400);
            $hours = intdiv($refreshSeconds % 86400, 3600);
            $minutes = intdiv($refreshSeconds % 3600, 60);
            $refreshAge = $days.' '.($days === 1 ? 'day' : 'days').' '.$hours.' '.($hours === 1 ? 'hour' : 'hours').' '.$minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }
    }
@endphp
<div class="page-head"><div class="shell"><div class="eyebrow">Daily pickup watch</div><h1>Daily Targets</h1><p>Players to target on {{ $selectedDate->format('M j, Y') }}</p>@if($refreshAge)<p class="tips-last-refreshed">Data last refreshed {{ $refreshAge }} ago.</p>@endif</div></div>
<div class="shell ai-tips">
<div class="toolbar tips-toolbar" role="group" aria-label="Game date"><div class="tips-date-buttons"><a class="button {{ $date===$today?'primary':'tips-date-inactive' }}" href="/daily-targets?date={{ $today }}">Today</a><a class="button {{ $date===$tomorrow?'primary':'tips-date-inactive' }}" href="/daily-targets?date={{ $tomorrow }}">Tomorrow</a></div></div>
@if(session('job_success') || session('job_error'))<section class="job-results" aria-live="polite">@if(session('job_success'))<div class="job-message job-message-ok">{{ session('job_success') }}</div>@endif @if(session('job_error'))<div class="job-message job-message-error">{{ session('job_error') }}</div>@endif</section>@endif
@if(!$hasTips)<div class="card"><h2>No tips available for this date</h2><p class="subtle">The collector tables do not currently contain qualifying available players for {{ $selectedDate->format('F j') }}.</p></div>
@else
<section class="tips-section" id="goalies"><div class="section-title tips-section-heading"><h2>Goaltenders</h2><div class="tips-section-links"><a class="tips-source-button" href="https://www.dailyfaceoff.com/starting-goalies/{{ $date }}" target="_blank" rel="noopener noreferrer"><img src="/dailyfaceoff-icon.png?v=4" alt="">Starting Goalies</a><a class="tips-source-button" href="{{ $fantraxGoaliesUrl }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" alt="">Fantrax Goalies</a></div></div><div class="table-card"><div class="table-scroll"><table class="data-table tips-table tips-goalie-table"><thead><tr><th>Goalie</th><th>Opponent</th><th>Status</th><th>Starting status</th><th>Odds</th><th>Proj. season FPts</th><th>Actions</th></tr></thead><tbody>
@forelse($groups['G'] as $player)<tr class="tips-result-row {{ !empty($player['not_starting']) ? 'tips-not-starting' : '' }} {{ !empty($player['injury_status']) ? 'tips-injured-player' : '' }}" data-injured="{{ !empty($player['injury_status']) ? '1' : '0' }}">@php($isAway=str_starts_with(trim($player['opponent']??''),'@')) @php($mobileOpponent=$isAway?$player['opponent']:'vs '.trim($player['opponent']??'')) @php($goalieDepth=$lineNumber($player))<td data-label="Goalie" data-opponent="{{ $mobileOpponent }}" class="tips-player-cell {{ $isAway?'tips-away':'tips-home' }}"><strong>@if(!empty($player['injury_status'])&&preg_match('/IR/i',$player['injury_status']))<span class="pill tips-ir">IR</span>@endif<span class="tips-player-name">{{ $player['name'] }} ({{ $player['team'] }})</span>@if($goalieDepth)<span class="pill tips-goalie-depth tips-g{{ $goalieDepth }}">G{{ $goalieDepth }}</span>@endif</strong></td><td data-label="Opponent">{{ $player['opponent'] }}</td><td data-label="Status"><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td><td data-label="Starting">@if(!empty($player['starting_status']))@php($startingClass=match(strtolower($player['starting_status'])){'confirmed'=>'tips-start-confirmed','probable'=>'tips-start-probable',default=>'tips-start-unconfirmed'})<span class="pill {{ $startingClass }}">{{ $player['starting_status'] }}</span>@else<span class="pill tips-start-na">NA</span>@endif</td>@php($teamOdds=$oddsByTeam[strtoupper(trim($player['team']))]??null)<td data-label="Odds">@if($teamOdds && $teamOdds->american_odds!==null)@php($oddsClass=$teamOdds->american_odds <= -130 ? 'tips-odds-good' : ($teamOdds->american_odds >= 130 ? 'tips-odds-bad' : 'tips-odds-even'))<span class="pill tips-odds {{ $oddsClass }}" title="Consensus from {{ $teamOdds->bookmaker_count }} bookmakers">{{ $teamOdds->american_odds > 0 ? '+' : '' }}{{ $teamOdds->american_odds }}</span>@else<span class="subtle">—</span>@endif</td><td data-label="Proj. FPts">@if($player['projected_points']!==null)<span class="tips-proj-value">{{ number_format($player['projected_points'],0) }}</span>@else<span class="tips-proj-value subtle">—</span>@endif</td><td data-label="Actions">@if(!empty($player['not_starting']))<span class="tips-add-button tips-add-disabled" role="link" aria-disabled="true" title="Another goalie is confirmed for this team">+ Add<img src="/fantrax-icon.png" alt=""></span>@else<a class="tips-add-button" href="{{ $fantraxPlayerUrl($player) }}" target="_blank" rel="noopener noreferrer">+ Add<img src="/fantrax-icon.png" alt=""></a>@endif</td></tr>@empty<tr><td colspan="7" class="empty">No qualifying available goalies in this update.</td></tr>@endforelse</tbody></table></div></div><button type="button" class="button tips-show-more" hidden>Show 10 more</button></section>
@foreach(['F'=>['forwards','Forwards'],'D'=>['defensemen','Defensemen']] as $position=>$section)<section class="tips-section" id="{{ $section[0] }}"><div class="section-title tips-section-heading"><h2>{{ $section[1] }}</h2><div class="tips-section-links"><a class="tips-source-button" href="https://www.dailyfaceoff.com/teams" target="_blank" rel="noopener noreferrer"><img src="/dailyfaceoff-icon.png?v=4" alt="">Line Combinations</a><a class="tips-source-button" href="{{ $position==='F' ? $fantraxForwardsUrl : $fantraxDefenseUrl }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" alt="">{{ $position==='F' ? 'Fantrax Forwards' : 'Fantrax Defensemen' }}</a></div></div><div class="table-card"><div class="table-scroll"><table class="data-table tips-table tips-skater-table"><thead><tr><th>#</th><th>Player</th><th>Opponent</th><th>Status</th><th>Proj. season FPts</th><th>Actions</th></tr></thead><tbody>
@forelse($groups[$position] as $player)<tr class="tips-result-row {{ !empty($player['injury_status']) ? 'tips-injured-player' : '' }}" data-injured="{{ !empty($player['injury_status']) ? '1' : '0' }}">@php($isAway=str_starts_with(trim($player['opponent']??''),'@')) @php($mobileOpponent=$isAway?$player['opponent']:'vs '.trim($player['opponent']??'')) @php($playerPpUnit=$ppUnit($player)) @php($playerLineNumber=$lineNumber($player))<td data-label="#">{{ $loop->iteration }}</td><td data-label="Player" data-opponent="{{ $mobileOpponent }}" class="tips-player-cell {{ $isAway?'tips-away':'tips-home' }}"><strong>@if(!empty($player['injury_status'])&&preg_match('/IR/i',$player['injury_status']))<span class="pill tips-ir">IR</span>@endif<span class="tips-player-with-pp"><span class="tips-player-name">{{ $player['name'] }} ({{ $player['team'] }})</span><span class="tips-line-badges">@if($playerLineNumber)<span class="pill tips-line tips-line-{{ $playerLineNumber }}">L{{ $playerLineNumber }}</span>@endif @if($playerPpUnit===1)<span class="pill tips-pp tips-pp1">PP1</span>@elseif($playerPpUnit===2)<span class="pill tips-pp tips-pp2">PP2</span>@endif</span></span></strong></td><td data-label="Opponent">{{ $player['opponent'] }}</td><td data-label="Status"><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td><td data-label="Proj. FPts"><span class="tips-proj-value">{{ number_format($player['projected_points'],0) }}</span></td><td data-label="Actions"><div class="tips-action-buttons"><a class="tips-icon-button tips-dfo-line-button" href="{{ $dfoLinesUrl($player['team']) }}" target="_blank" rel="noopener noreferrer" aria-label="Daily Faceoff line combinations for {{ $player['team'] }}" title="Daily Faceoff line combinations"><img src="/dailyfaceoff-icon.png?v=4" alt=""></a><a class="tips-add-button" href="{{ $fantraxPlayerUrl($player) }}" target="_blank" rel="noopener noreferrer">+ Add<img src="/fantrax-icon.png" alt=""></a></div></td></tr>@empty<tr><td colspan="6" class="empty">No qualifying available players in this update.</td></tr>@endforelse</tbody></table></div></div><button type="button" class="button tips-show-more" hidden>Show 10 more</button></section>@endforeach
@if(isset($fantasyTeams) && $fantasyTeams->count())
<section class="tips-section fantasy-team-sections" id="fantasy-teams">
  <div class="section-title tips-section-heading">
    <h2>Fantasy Teams</h2>
    <p class="subtle">Current rosters for {{ $selectedDate->format('M j') }}. Players with a game are grouped ahead of players not playing.</p>
  </div>
  <div class="fantasy-team-grid">
    @foreach($fantasyTeams as $teamId => $teamRows)
      @php($teamName=$teamRows->first()->fantasy_team_name)
      <details class="card fantasy-team-card">
        <summary><strong>{{ $teamName }}</strong><span class="subtle">{{ $teamRows->count() }} players</span></summary>
        <div class="fantasy-team-roster">
          @foreach(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $pos=>$posLabel)
            @php($positionRows=$teamRows->where('position',$pos))
            @if($positionRows->count())
              <div class="fantasy-position-group">
                <h3>{{ $posLabel }}</h3>
                @foreach([1=>'Playing',0=>'Not Playing'] as $playingFlag=>$playingLabel)
                  @php($statusRows=$positionRows->filter(fn($p)=>(!empty($p->opponent)?1:0)===$playingFlag))
                  @if($statusRows->count())
                    <div class="fantasy-playing-group">
                      <div class="fantasy-playing-label">{{ $playingLabel }}</div>
                      @foreach($statusRows as $rosterPlayer)
                        @php($metaPlayer=['team'=>$rosterPlayer->nhl_team,'name'=>$rosterPlayer->player_name,'position'=>$rosterPlayer->position])
                        @php($rosterLine=$pos!=='G'?$lineNumber($metaPlayer):null)
                        @php($rosterPp=$pos!=='G'?$ppUnit($metaPlayer):null)
                        <div class="fantasy-player-row">
                          <div class="fantasy-player-main">
                            <div class="fantasy-player-name">
                              <strong>{{ $rosterPlayer->player_name }} @if($rosterPlayer->nhl_team)({{ $rosterPlayer->nhl_team }})@endif</strong>
                              <span class="fantasy-badges">
                                @if($rosterPlayer->is_ir)<span class="pill tips-ir">IR</span>@endif
                                @if($rosterPlayer->is_bench)<span class="pill fantasy-bench">BE</span>@endif
                                @if(strtoupper((string)$rosterPlayer->roster_status)==='MINORS')<span class="pill fantasy-minors">MIN</span>@endif
                                @if($rosterLine && $rosterLine>=1 && $rosterLine<=4)<span class="pill tips-line tips-line-{{ $rosterLine }}">L{{ $rosterLine }}</span>@endif
                                @if($rosterPp===1)<span class="pill tips-pp tips-pp1">PP1</span>@elseif($rosterPp===2)<span class="pill tips-pp tips-pp2">PP2</span>@endif
                              </span>
                            </div>
                            <div class="fantasy-player-opponent">
                              @if($rosterPlayer->opponent)
                                <span class="{{ $rosterPlayer->home_away==='AWAY'?'tips-away-text':'tips-home-text' }}">{{ $rosterPlayer->home_away==='AWAY'?'@':'vs' }} {{ $rosterPlayer->opponent }}</span>
                              @else
                                <span class="subtle">No game</span>
                              @endif
                            </div>
                          </div>
                          <div class="fantasy-player-points">
                            <span>Proj. FPts</span>
                            <strong>{{ $rosterPlayer->projected_fpts!==null ? number_format($rosterPlayer->projected_fpts,0) : '—' }}</strong>
                          </div>
                        </div>
                      @endforeach
                    </div>
                  @endif
                @endforeach
              </div>
            @endif
          @endforeach
        </div>
      </details>
    @endforeach
  </div>
</section>
@endif
<p class="subtle tips-method">Players are on teams scheduled to play; individual lineup spots are not confirmed unless a goalie starting status is shown. Projections cover the full season, not a single game.</p><div class="filter-group tips-sources"><span>Sources</span><a class="filter-button tips-source-button" href="https://www.dailyfaceoff.com/starting-goalies/{{ $date }}" target="_blank" rel="noopener noreferrer"><img src="/dailyfaceoff-icon.png?v=4" alt="">Daily Faceoff ↗</a><a class="filter-button tips-source-button" href="{{ $fantraxListingUrl }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" alt="">Fantrax ↗</a></div><div class="tips-updated subtle">@if($latestUpdated)Updated {{ \Carbon\CarbonImmutable::parse($latestUpdated)->setTimezone('America/Halifax')->format('M j, Y · g:i a T') }} @endif<a href="/job-status" class="tips-refresh-status tips-refresh-success">Collector status</a></div>@endif</div>
<style>
.tips-toolbar{align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px}.tips-date-buttons{display:flex;gap:6px}.tips-date-buttons .button{padding:6px 10px;font-size:12px;min-height:auto}.tips-last-refreshed{margin:5px 0 0;font-size:12px;opacity:.8}.tips-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.tips-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.tips-section{margin:20px 0;scroll-margin-top:95px}.tips-section-heading{display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-start;gap:7px}.tips-section-heading h2{margin:0}.tips-section-links{display:flex;align-items:center;justify-content:flex-start;gap:8px;flex-wrap:wrap;width:100%}.tips-source-button{display:inline-flex;align-items:center;gap:7px;text-decoration:none!important}.tips-source-button img[src="/dailyfaceoff-icon.png?v=4"]{width:24px;height:24px;border-radius:50%;object-fit:cover}.tips-source-button img[src="/fantrax-icon.png"]{width:18px;height:18px;border-radius:50%;object-fit:contain}.tips-action-buttons{display:flex;align-items:center;justify-content:flex-end;gap:6px}.tips-icon-button{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid var(--line);border-radius:8px;background:var(--surface);text-decoration:none}.tips-icon-button img{width:22px;height:22px;border-radius:50%;object-fit:cover}.tips-icon-button:hover{filter:brightness(.96)}.tips-table th,.tips-table td{padding:7px 9px!important;font-size:12px}.tips-section .section-title p{margin:5px 0 0;font-size:13px}.tips-waiver{background:var(--accent-soft);border-color:var(--accent)}.tips-fa{color:var(--success);font-weight:700}.tips-ir{background:#dc2626;color:#fff;border-color:#dc2626;font-weight:800;margin-right:6px;padding:2px 6px!important;font-size:10px!important;line-height:1.1;border-radius:999px}.tips-player-with-pp{display:inline-flex;align-items:center;gap:6px;min-width:0}.tips-line-badges{display:inline-flex;align-items:center;gap:5px;flex:0 0 auto}.tips-line{font-weight:800}.tips-line-1{background:#dcfce7;color:#166534;border-color:#86efac}.tips-line-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.tips-line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.tips-line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.tips-pp{flex:0 0 auto;font-weight:800}.tips-pp1{background:#dcfce7;color:#166534;border-color:#86efac}.tips-pp2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.tips-goalie-depth{margin-left:6px;font-weight:800}.tips-g1{background:#dcfce7;color:#166534;border-color:#86efac}.tips-g2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.tips-start-confirmed{background:#16a34a;color:#fff;border-color:#15803d;font-weight:800}.tips-start-probable{background:#facc15;color:#422006;border-color:#eab308;font-weight:800}.tips-start-unconfirmed,.tips-start-na{background:#e5e7eb;color:#374151;border-color:#d1d5db;font-weight:700}.tips-odds{font-weight:800}.tips-odds-good{background:#dcfce7;color:#166534;border-color:#86efac}.tips-odds-even{background:#fef3c7;color:#92400e;border-color:#fcd34d}.tips-odds-bad{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.tips-add-button{display:inline-flex;align-items:center;justify-content:center;gap:5px;background:#0055A7;color:#fff!important;border:1px solid #004786;border-radius:8px;padding:5px 9px;font-weight:800;font-size:11px;text-decoration:none;white-space:nowrap}.tips-add-button img{width:15px;height:15px;object-fit:contain}.tips-add-button:hover{background:#004786;text-decoration:none}.tips-table td:first-child{white-space:normal}.tips-method{font-size:13px}.tips-sources{margin-top:18px}.tips-updated{margin-top:24px;padding:14px 0 4px;border-top:1px solid var(--line);font-size:12px;text-align:right}.tips-refresh-status{display:inline-block;margin-left:8px;padding:3px 7px;border-radius:999px;font-weight:800;text-decoration:none}.tips-refresh-status:hover{text-decoration:none;filter:brightness(.96)}.tips-refresh-success{background:#dcfce7;border:1px solid #86efac;color:#166534}.tips-table .pill{white-space:nowrap}.ai-tips .table-scroll{overflow-x:auto}.tips-show-more{display:block;margin:12px auto 0;cursor:pointer}.tips-show-more[hidden]{display:none}.tips-result-row[hidden]{display:none!important}.job-results{margin:0 0 18px}.job-message{padding:12px 14px;border-radius:10px;font-weight:700;white-space:pre-wrap}.job-message-ok{background:#dcfce7;color:#166534;border:1px solid #86efac}.job-message-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
@media(max-width:600px){.tips-toolbar{align-items:flex-start;flex-direction:column}.tips-date-buttons{width:auto}.tips-date-buttons .button{flex:0 0 auto;text-align:center}.ai-tips .table-card{background:transparent;border:0;box-shadow:none;overflow:visible}.ai-tips .table-scroll{overflow:visible}.tips-table,.tips-table tbody{display:block;width:100%}.tips-table thead{display:none}.tips-table tr{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-rows:auto auto;column-gap:10px;row-gap:8px;align-items:center;background:var(--surface);border:1px solid var(--line);border-radius:12px;margin-bottom:8px;padding:10px;box-shadow:0 2px 8px rgba(15,23,42,.05);overflow:hidden;position:relative}.tips-table td{border:0!important;padding:0!important;font-size:13px;white-space:normal}.tips-table td::before{display:none}.tips-table td[data-label="Goalie"],.tips-table td[data-label="Player"]{grid-column:1;grid-row:1;min-width:0;text-align:left;background:transparent!important;border:0!important;padding-right:72px!important}.tips-player-cell strong{display:flex;align-items:center;min-width:0;font-size:16px;line-height:1.2}.tips-player-name{min-width:0}.tips-player-with-pp{display:inline-flex;align-items:center;min-width:0}.tips-skater-table .tips-player-with-pp .tips-line-badges{position:absolute;left:10px;bottom:13px}.tips-player-cell::after{content:attr(data-opponent);display:block;margin-top:5px;font-size:12px;font-weight:800;line-height:1}.tips-player-cell.tips-away::after{color:#a16207}.tips-player-cell.tips-home::after{color:#15803d}.tips-table td[data-label="Opponent"]{display:none}.tips-table td[data-label="Proj. FPts"]{position:absolute;right:10px;top:10px;text-align:right;min-width:54px;padding-left:10px!important;border-left:1px solid var(--line)!important}.tips-table td[data-label="Proj. FPts"]::before{display:block;content:"Proj. Pts";color:var(--muted);font-size:10px;font-weight:700;white-space:nowrap;margin-bottom:1px}.tips-proj-value{display:block;font-size:22px;line-height:1;font-weight:800;color:var(--text)}.tips-table td[data-label="Actions"]{position:absolute;right:10px;bottom:9px;margin:0}.tips-table td[data-label="Status"]{position:absolute;right:128px;bottom:12px;margin:0}.tips-goalie-table td[data-label="Status"]{right:92px}.tips-goalie-table td[data-label="Starting"]{position:absolute;left:10px;bottom:12px;margin:0}.tips-goalie-table td[data-label="Odds"]{position:absolute;left:112px;bottom:12px;margin:0}.tips-skater-table td[data-label="#"]{display:none}.tips-skater-table tr{min-height:98px;padding-bottom:42px}.tips-goalie-table tr{min-height:102px;padding-bottom:42px}.tips-table .pill{padding:5px 9px;font-size:11px}.tips-table td[data-label="Status"] .tips-waiver{background:#fff7d6;color:#713f12;border-color:#eab308;font-weight:800}.tips-table td[data-label="Status"] .tips-fa{display:inline-flex;background:#e5e7eb;color:#374151;border:1px solid #d1d5db;font-weight:800}.tips-ir{flex:0 0 auto;margin:0 6px 0 0;background:#dc2626!important;color:#fff!important;border-color:#dc2626!important;padding:2px 6px!important;font-size:10px!important;line-height:1.1}.tips-line,.tips-pp{padding:4px 7px!important;font-size:10px!important}.tips-add-button{min-width:64px;padding:6px 9px;background:#0055A7;border-color:#004786;font-size:11px}.tips-section-heading{align-items:flex-start}.tips-section-links{gap:5px}.tips-source-button{font-size:11px;padding:5px 7px!important}.tips-icon-button{width:28px;height:28px}.tips-icon-button img{width:20px;height:20px}.tips-section h2{font-size:21px}.tips-updated{text-align:left}.tips-refresh-status{margin:6px 0 0;display:table}.tips-sources{gap:7px}.tips-sources>span{width:100%}}
.fantasy-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.fantasy-team-card{padding:0;overflow:hidden}.fantasy-team-card summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;cursor:pointer;list-style:none}.fantasy-team-card summary::-webkit-details-marker{display:none}.fantasy-team-card summary::after{content:'+';font-weight:900;font-size:20px}.fantasy-team-card[open] summary::after{content:'−'}.fantasy-team-roster{border-top:1px solid var(--line);padding:0 14px 14px}.fantasy-position-group h3{margin:14px 0 7px;font-size:15px}.fantasy-playing-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:800;margin:9px 0 5px}.fantasy-player-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid var(--line)}.fantasy-player-main{min-width:0}.fantasy-player-name{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:13px}.fantasy-badges{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}.fantasy-player-opponent{font-size:11px;margin-top:3px}.tips-away-text{color:#a16207;font-weight:800}.tips-home-text{color:#15803d;font-weight:800}.fantasy-player-points{text-align:right;flex:0 0 auto}.fantasy-player-points span{display:block;color:var(--muted);font-size:9px;font-weight:700}.fantasy-player-points strong{font-size:17px}.fantasy-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db;font-weight:800}.fantasy-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd;font-weight:800}@media(max-width:800px){.fantasy-team-grid{grid-template-columns:1fr}.fantasy-team-card summary{padding:14px}.fantasy-team-roster{padding:0 12px 12px}.fantasy-player-name{font-size:12px}.fantasy-player-points strong{font-size:16px}}
@media(min-width:851px) and (max-width:1100px){.main-nav a{padding:8px 5px;font-size:12px}.nav-wrap{gap:8px}}
.tips-table tr.tips-not-starting{opacity:.55;filter:grayscale(1)}.tips-add-button.tips-add-disabled{background:#6b7280;border-color:#6b7280;cursor:not-allowed}
</style>
@endsection
