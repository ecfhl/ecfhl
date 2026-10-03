@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><h1>Choose your team</h1><p>Finish setting up your owner account.</p></div></div>
<div class="shell owner-page">@include('account.shared')
@if(auth()->user()->claim)<p class="owner-notice">Your account already owns {{ auth()->user()->claim->team_name }}.</p><a class="button" href="/account">Account</a>
@else<form method="post" action="/account/claim-team" class="card owner-form">@csrf @include('account.team-picker')<button class="button primary">Claim team</button></form>@endif
</div>
@endsection
