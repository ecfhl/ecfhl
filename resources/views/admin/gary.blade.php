@extends('layouts.app')
@section('title', 'Gary Betman · ECFHL')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Gary Betman</h1><p>Fictional league messaging persona.</p></div></div>
<div class="shell" style="max-width:720px;padding-bottom:28px">
@if(session('notice'))<div class="card" role="status">{{ session('notice') }}</div>@endif
<form class="card owner-form" method="post" action="/admin/gary/messages" style="padding:20px;margin-top:16px">
 @csrf<input type="hidden" name="client_id" value="{{ old('client_id', (string)\Illuminate\Support\Str::uuid()) }}">
 <label for="gary-recipient">Send to</label><select id="gary-recipient" name="user_id">
 <option value="{{ $owner->id }}" @selected((string)old('user_id',$owner->id)===(string)$owner->id)>Me · {{ $owner->claim?->team_name ?? $owner->name }}</option>
 @foreach($people->where('id','!=',$owner->id)->whereNull('messaging_persona') as $person)<option value="{{ $person->id }}" @selected((string)old('user_id')===(string)$person->id)>{{ $person->claim->team_name }}</option>@endforeach
 <option value="" @selected(old('user_id','not-league')==='')>League chat · everyone</option>
 </select>
 <label for="gary-message">Message from Gary</label><textarea id="gary-message" name="body" maxlength="4000" rows="5" required>{{ old('body') }}</textarea>
 <button type="submit" class="button primary">Send as Gary Betman</button>
 <p class="subtle">Gary uses the same private-message notifications and read receipts as other conversations. Only league administrators can send as Gary.</p>
</form></div>
@endsection
