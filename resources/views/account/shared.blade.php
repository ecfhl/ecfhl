<link rel="stylesheet" href="/owners.css?v=2">
@if(session('notice'))<p class="owner-notice" role="status">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="owner-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
