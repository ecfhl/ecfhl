@extends('layouts.app')
@section('title','Rules · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">League bylaws</div><h1>Rules</h1><p>Core ECFHL roster, transaction and playoff rules.</p></div>
<div class="season-list">@foreach($sections as $title=>$items)<section class="card"><h3>{{ $title }}</h3>@foreach($items as $item)<p>{{ $item }}</p>@endforeach</section>@endforeach</div></div>
@endsection