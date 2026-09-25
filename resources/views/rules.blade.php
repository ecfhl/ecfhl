@extends('layouts.app')
@section('title','Rules · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">League bylaws</div><h1>Rules</h1><p>Core ECFHL roster, transaction and playoff rules.</p></div>

  <div class="rules-grid">
    @foreach($sections as $title=>$items)
      @php
        $icon = match(true) {
          str_contains(strtolower($title),'roster') => '👥',
          str_contains(strtolower($title),'draft') => '📝',
          str_contains(strtolower($title),'trade') => '🔄',
          str_contains(strtolower($title),'playoff') => '🏆',
          str_contains(strtolower($title),'goalie') => '🥅',
          str_contains(strtolower($title),'minor') => '🌱',
          default => '📘'
        };
      @endphp
      <section class="card rule-card">
        <div class="rule-card-head"><span>{{ $icon }}</span><h2>{{ $title ?: 'General' }}</h2></div>
        <ul class="rule-list unnumbered">
          @foreach($items as $item)<li>{{ $item }}</li>@endforeach
        </ul>
      </section>
    @endforeach
  </div>
</div>
@endsection
