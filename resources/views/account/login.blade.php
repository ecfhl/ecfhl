@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">League owners</div><h1>Sign in</h1><p>Your team, your alerts.</p></div></div>
<div class="shell owner-page owner-narrow">@include('account.shared')
@if($googleReady)<a class="button owner-google" href="/auth/google">Continue with Google</a><p class="subtle">Or sign in with your email.</p>@endif
<form method="post" action="/login" class="card owner-form">@csrf
<label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="username"></label>
<label>Password<input name="password" type="password" required autocomplete="current-password"></label>
<label class="owner-checkbox"><input type="checkbox" name="remember" value="1" checked>Keep me signed in</label>
<button class="button primary" type="submit">Sign in</button>
</form><div class="login-create-account"><p>New to ECFHL?</p><a class="button primary" href="/register">Create account</a></div><p><a href="/teams/current">Continue browsing</a></p></div>
@endsection
