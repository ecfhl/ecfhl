@php
  $liveOppText = trim((string)($player->live_opponent_display ?? ''));
  $isGameFinished = !empty($player->game_finished)
    || ($liveOppText !== '' && preg_match('/(?:\bF\b|\bFinal\b)\s*$/i', $liveOppText));
@endphp
<div class="matchup-player-row {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }} {{ strtoupper((string)$player->roster_status)==='MINORS'?'team-minors-row':'' }} {{ $isGameFinished?'team-game-finished-row':'' }}" @if($isGameFinished) style="background:#fffbea !important" @endif>
  <div class="matchup-player-main">
    <div class="matchup-player-name">
      @if($player->is_ir)<span class="pill team-ir">IR</span>@endif
      <strong>{{ $player->player_name }} @if($player->nhl_team)({{ $player->nhl_team }})@endif</strong>
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
        $showLiveOpp = (($player->today_gp ?? 0) > 0) && !empty($player->live_opponent_display);
      @endphp
      @if($showLiveOpp)
        <span class="team-playing-text">{{ $player->live_opponent_display }}</span>
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
      <span class="matchup-player-cats">@foreach(['today_gp'=>'GP','today_g'=>'G','today_a'=>'A','today_ppg'=>'PPG','today_shg'=>'SHG','today_gwg'=>'GWG','today_w'=>'W','today_so'=>'SO'] as $field=>$label)@if(($player->{$field} ?? 0) != 0)<span>{{ $label }}: {{ $player->{$field} }}</span>@endif @endforeach</span>
    </div>
  </div>
  <div class="matchup-player-metrics">
    <div><span>Proj./G</span><strong>{{ $player->projected_fpts_per_game!==null?number_format($player->projected_fpts_per_game,2):'—' }}</strong></div>
    <div class="matchup-player-today"><span>Today</span><strong class="{{ !empty($player->today_fpts_changed)?'score-changed':'' }}">{{ number_format($player->today_fpts ?? 0,0) }}</strong></div>
  </div>
</div>
