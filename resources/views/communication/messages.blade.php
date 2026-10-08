@extends('layouts.app')
@section('content')
<div class="shell owner-page messages-page"><h1>Messages</h1>
<label>Conversation <select id="chat-conversation-select" onchange="location.href=this.value ? '/messages?user_id='+encodeURIComponent(this.value) : '/messages'">
 <option value="" @selected(!$other)>League chat</option>
 @foreach($people as $person)<option value="{{ $person->id }}" @selected($other===$person->id)>{{ $person->claim?->team_name ?? $person->name }} · {{ $person->name }}</option>@endforeach
</select></label>
@include('communication.chat-widget',['chatOther'=>$other,'chatTitle'=>$other ? 'Private conversation with '.($people->firstWhere('id',$other)?->claim?->team_name ?? $people->firstWhere('id',$other)?->name) : 'League chat'])
<details class="card message-preferences"><summary>Message notification settings</summary>
@foreach(['private_message_popups'=>'Private message popups','private_message_push'=>'Private message push notifications','league_message_popups'=>'League chat popups','league_message_push'=>'League chat push notifications'] as $key=>$label)
<label class="communication-setting"><input type="checkbox" data-message-preference="{{ $key }}" checked> {{ $label }}</label>
@endforeach
<p class="subtle">Push notifications also require browser permission. Enable them using the bell in the header.</p><p id="message-preferences-status" role="status"></p>
</details></div>
@endsection
