@extends('layouts.app')
@section('content')
<div class="shell owner-page messages-page"><h1>Messages</h1>
@if($other)<a class="message-back" href="/messages">← League chat</a>@endif
@include('communication.chat-widget',['chatOther'=>$other,'chatTitle'=>$other ? 'Private conversation with '.($people->firstWhere('id',$other)?->claim?->team_name ?? $people->firstWhere('id',$other)?->name ?? \App\Models\User::find($other)?->name) : 'League chat'])
<a class="message-settings-link" href="/notifications">Notification / alert settings →</a>
</div>
@endsection
