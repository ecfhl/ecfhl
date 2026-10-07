@php
  $liveOppText = trim((string)($player->live_opponent_display ?? ''));
  $rosterGameText = trim((string)($player->game_time ?? ''));
  $finalCheckText = trim($liveOppText.' '.$rosterGameText);
  // Final-game marker is redundant on the player row and can wrap onto its own line as "F".
  $liveOppText = preg_replace('/(?:\\s*[·|-]?\\s*)(?:F|Final)\\s*$/i', '', $liveOppText);
  // Normalize live team scoring so only the first team is prefixed with @.
  $liveOppText = preg_replace('/\\s+@(?=[A-Z]{2,4}\\b)/', ' ', $liveOppText);
  $isGameFinished = !empty($player->game_finished)
    || ($finalCheckText !== '' && preg_match('/(?:\bF\b|\bFinal\b)\s*$/i', $finalCheckText));
  $gameStateClass = $isGameFinished ? 'team-game-finished-row' : (!empty($player->game_in_progress) ? 'team-game-live-row' : 'team-game-upcoming-row');
@endphp
<div class="matchup-player-row {{ $gameStateClass }} {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }} {{ strtoupper((string)$player->roster_status)==='MINORS'?'team-minors-row':'' }}">
  <div class="matchup-player-main">
    <div class="matchup-player-name">
      @if($player->is_ir)<span class="pill team-ir">IR</span>@endif
      <strong><a class="player-name-link" data-player-stats href="/players/{{ rawurlencode($player->player_id) }}">{{ \App\Support\PlayerName::display($player->player_name) }} @if($player->nhl_team)({{ $player->nhl_team }})@endif</a></strong>
      @if($player->is_bench)<span class="pill team-bench">Bench</span>@endif
      @if(!empty($player->contract_label))<span class="pill team-contract-sticker {{ $player->contract_class }}">{{ $player->contract_label }}</span>@endif
      @if($player->line_number)
        @if(strtoupper((string)$player->position)==='G' && $player->line_number<=2)
          <span class="pill goalie-{{ $player->line_number }}">G{{ $player->line_number }}</span>
          @if($player->vegas_odds!==null)<span class="pill goalie-vegas-odds {{ $player->vegas_odds_class }}">{{ $player->vegas_odds>0?'+':'' }}{{ $player->vegas_odds }}</span>@endif
        @elseif($player->line_number<=4)
          <span class="pill line-{{ $player->line_number }}">L{{ $player->line_number }}</span>
        @endif
      @endif
      @if($player->pp_unit===1)<span class="pill pp1">PP1</span>@elseif($player->pp_unit===2)<span class="pill pp2">PP2</span>@endif
    </div>
    <div class="matchup-player-opponent">
      @php
        $showLiveOpp = !empty($player->live_opponent_display);
      @endphp
      @if($showLiveOpp)
        <span class="team-playing-text">{{ $liveOppText }}</span>
      @elseif($player->opponent)
        @php
          $displayGameTime = $player->game_time
            ? preg_replace('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+/i', '', trim((string)$player->game_time))
            : null;
        @endphp
        <span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($displayGameTime) · {{ $displayGameTime }}@endif</span>
      @else
        <span class="team-playing-text">Playing</span>
      @endif
      @php($statLine = \App\Support\LiveScoring\PlayerStatLine::text($player, $isGameFinished || !empty($player->game_in_progress)))
      @if($statLine!=='')<span class="matchup-player-cats">{{ $statLine }}</span>@endif
    </div>
  </div>
  <div class="matchup-player-metrics">
    <div class="matchup-player-today"><span>Day</span><strong class="score-{{ $player->today_fpts_change ?? 'same' }}">{{ rtrim(rtrim(number_format($player->today_fpts ?? 0,2), '0'), '.') }}</strong></div>
  </div>
</div>
