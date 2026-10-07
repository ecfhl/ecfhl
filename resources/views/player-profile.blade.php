@extends('layouts.app')
@section('content')
<div class="shell player-profile-page"><a class="player-profile-back" href="/players">← Players</a>@include('partials.player-profile')</div>
<link rel="stylesheet" href="/player-profile.css?v={{ hash_file('sha256', base_path('public/player-profile.css')) }}">
@endsection
