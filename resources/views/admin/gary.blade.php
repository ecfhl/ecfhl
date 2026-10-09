@extends('layouts.app')
@section('title', 'Gary Bettman · ECFHL')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1 style="display:flex;align-items:center;gap:12px"><img src="{{ \App\Support\TeamImages::url('gary-bettman',160) }}" alt="Gary Bettman" width="64" height="64" style="border-radius:12px">Gary Bettman</h1><p>Fictional league messaging persona.</p></div></div>
<div class="shell" style="max-width:720px;padding-bottom:28px">
@if(session('notice'))<div class="card" role="status">{{ session('notice') }}</div>@endif
<form class="card owner-form" method="post" action="/admin/gary/messages" style="padding:20px;margin-top:16px;display:grid;gap:12px;min-width:0">
 @csrf<input type="hidden" name="client_id" value="{{ old('client_id', (string)\Illuminate\Support\Str::uuid()) }}">
 <label for="gary-recipient" style="display:block;margin:0">Send to</label><select id="gary-recipient" name="user_id" style="display:block;width:100%;max-width:100%;min-width:0;box-sizing:border-box">
 <option value="{{ $owner->id }}" @selected((string)old('user_id',$owner->id)===(string)$owner->id)>Me · {{ $owner->claim?->team_name ?? $owner->name }}</option>
 @foreach($people->where('id','!=',$owner->id)->whereNull('messaging_persona') as $person)<option value="{{ $person->id }}" @selected((string)old('user_id')===(string)$person->id)>{{ $person->claim->team_name }}</option>@endforeach
 <option value="" @selected(old('user_id','not-league')==='')>League chat · everyone</option>
 </select>
 <label for="gary-message" style="display:block;margin:0">Message from Gary</label><textarea id="gary-message" name="body" maxlength="4000" rows="5" required style="display:block;width:100%;max-width:100%;min-width:0;box-sizing:border-box">{{ old('body') }}</textarea>
 <button type="submit" class="button primary" style="justify-self:start;max-width:100%">Send as Gary Bettman</button>
 <p class="subtle">Gary uses the same private-message notifications and read receipts as other conversations. Only league administrators can send as Gary.</p>
</form></div>
@endsection
