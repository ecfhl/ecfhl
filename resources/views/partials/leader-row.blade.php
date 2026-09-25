<div class="leader-rank"><span>{{ $i+1 }}</span><strong>{{ $row['team'] }}
@if(!empty($row['season']))<small><a href="/seasons/{{ rawurlencode($row['season']) }}">{{ $row['season'] }}</a></small>@endif
@if(!empty($row['detail']))<small>{{ $row['detail'] }}</small>@endif
</strong><b>{{ $row['value'] }}</b></div>