<div class="matchup-player-row {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }} {{ strtoupper((string)$player->roster_status)==='MINORS'?'team-minors-row':'' }}">
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
      @if($player->opponent)
        <span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($player->game_time) · {{ $player->game_time }}@endif</span>
      @else
        <span class="team-playing-text">Playing</span>
      @endif
    </div>
  </div>
  <div class="matchup-player-metrics">
    <div><span>Proj./G</span><strong>{{ $player->projected_fpts_per_game!==null?number_format($player->projected_fpts_per_game,2):'—' }}</strong></div>
    <div class="matchup-player-today"><span>Today</span><strong>{{ number_format($player->today_fpts ?? 0,0) }}</strong></div>
  </div>
</div>
