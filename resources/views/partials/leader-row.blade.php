@php
$franchiseId = null;
if (empty($playerLink) && !empty($row['team'])) {
    $franchiseId = app(\App\Support\Archive::class)->franchiseId($row['team']);
}
@endphp
<div class="leader-rank"><span>{{ $i+1 }}</span><strong>@if(!empty($playerLink))@include('partials.player-link',['name'=>$row['team']])@elseif($franchiseId)<a class="franchise-link" href="/teams/{{ rawurlencode($franchiseId) }}">{{ $row['team'] }}</a>@else{{ $row['team'] }}@endif
@if(!empty($row['season']))<small><a href="/seasons/{{ rawurlencode($row['season']) }}">{{ $row['season'] }}</a></small>@endif
@if(!empty($row['detail']))<small>{{ $row['detail'] }}</small>@endif
</strong><b>{{ $row['value'] }}</b></div>