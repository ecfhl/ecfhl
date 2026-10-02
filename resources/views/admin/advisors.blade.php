@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Advisors</h1><p>Manage Lineup Advisor personalities, images and recommendation style.</p></div></div>

<div class="shell admin-advisors">
  <div class="admin-toolbar">
    <a class="button" href="/job-status">Collector Status</a>
    <a class="button primary" href="#add-advisor">Add Advisor</a>
  </div>

  @if(session('notice'))
    <div class="admin-notice">{{ session('notice') }}</div>
  @endif

  <div class="advisor-admin-grid">
    @foreach($advisors as $advisor)
      @php
        $imageSlug=$advisor->advisor_key==='mike'?'lineup-advisor':'lineup-advisor-'.$advisor->advisor_key;
      @endphp
      <section class="card advisor-admin-card">
        <div class="advisor-admin-head">
          <button
            type="button"
            class="advisor-admin-image"
            data-team-icon-viewer
            data-team-slug="{{ $imageSlug }}"
            data-advisor-key="{{ $advisor->advisor_key }}"
            data-advisor-first-name="{{ $advisor->first_name }}"
            title="View {{ $advisor->first_name }} image">
            <img src="/team-icons/{{ $imageSlug }}?v={{ now()->timestamp }}" alt="{{ $advisor->first_name }}">
            <span>Preview</span>
          </button>
          <div>
            <h2>{{ $advisor->first_name }}</h2>
            <div class="subtle">{{ $advisor->advisor_key }}</div>
            @if($advisor->is_conservative)
              <span class="pill advisor-conservative">Conservative</span>
            @endif
          </div>
        </div>

        <form method="POST" action="/admin/advisors/{{ $advisor->advisor_key }}/image" enctype="multipart/form-data" class="advisor-image-form">
          @csrf
          <label class="advisor-image-label">
            <span>Advisor Image</span>
            <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
          </label>
          <button class="button" type="submit">Upload / Change Image</button>
        </form>

        <form method="POST" action="/admin/advisors/{{ $advisor->advisor_key }}" class="advisor-edit-form">
          @csrf
          <label>
            <span>First Name</span>
            <input type="text" name="first_name" maxlength="40" value="{{ $advisor->first_name }}" required>
          </label>

          <label>
            <span>Style</span>
            <textarea name="style_text" rows="7" maxlength="5000" placeholder="One template per line. Use {advice} or {advice_lower}.">{{ $advisor->style_text }}</textarea>
          </label>

          <label class="advisor-check">
            <input type="checkbox" name="is_conservative" value="1" {{ $advisor->is_conservative?'checked':'' }}>
            <span>Conservative — more likely to recommend saving a move when the remaining option is weak.</span>
          </label>

          <div class="advisor-form-actions">
            <button class="button primary" type="submit">Save Changes</button>
          </div>
        </form>

        <form method="POST" action="/admin/advisors/{{ $advisor->advisor_key }}" onsubmit="return confirm('Remove {{ addslashes($advisor->first_name) }}?');">
          @csrf
          @method('DELETE')
          <button class="button advisor-delete" type="submit" {{ $advisors->count()<=1?'disabled':'' }}>Remove Advisor</button>
        </form>
      </section>
    @endforeach
  </div>

  <section class="card add-advisor-card" id="add-advisor">
    <h2>Add Advisor</h2>
    <p class="subtle">After adding the advisor, click their image card to upload a custom image. The regeneration job will automatically include them.</p>
    <form method="POST" action="/admin/advisors" class="advisor-edit-form">
      @csrf
      <label>
        <span>First Name</span>
        <input type="text" name="first_name" maxlength="40" required>
      </label>
      <label>
        <span>Style</span>
        <textarea name="style_text" rows="6" maxlength="5000" placeholder="Example: Listen. {advice}&#10;Here is the thing. {advice}"></textarea>
      </label>
      <label class="advisor-check">
        <input type="checkbox" name="is_conservative" value="1">
        <span>Conservative</span>
      </label>
      <button class="button primary" type="submit">Add Advisor</button>
    </form>
  </section>
</div>

<style>
.admin-advisors{padding-bottom:32px}.admin-toolbar{display:flex;gap:10px;justify-content:flex-end;margin:18px 0}.admin-notice{margin:0 0 16px;padding:10px 12px;border:1px solid #86efac;border-radius:9px;background:#f0fdf4;color:#166534;font-weight:700}.advisor-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.advisor-admin-card{padding:18px}.advisor-admin-head{display:flex;gap:14px;align-items:center;margin-bottom:16px}.advisor-admin-head h2{margin:0 0 4px}.advisor-admin-image{position:relative;width:92px;height:92px;padding:0;border:1px solid var(--line);border-radius:12px;overflow:hidden;background:#f8fafc;cursor:pointer;flex:0 0 auto}.advisor-admin-image img{width:100%;height:100%;object-fit:cover;display:block}.advisor-admin-image span{position:absolute;left:0;right:0;bottom:0;padding:4px;background:rgba(15,23,42,.78);color:#fff;font-size:10px;font-weight:800}.advisor-conservative{margin-top:7px;background:#fef3c7;color:#92400e;border-color:#fcd34d}.advisor-image-form{display:flex;align-items:end;gap:10px;margin:0 0 14px;padding:10px;border:1px solid var(--line);border-radius:9px;background:var(--surface-2,#f8fafc)}.advisor-image-label{flex:1}.advisor-image-label>span{display:block;margin-bottom:5px;font-size:12px;font-weight:800;color:var(--muted)}.advisor-image-label input{width:100%;font-size:12px}.advisor-edit-form{display:grid;gap:12px}.advisor-edit-form label>span{display:block;margin-bottom:5px;font-size:12px;font-weight:800;color:var(--muted)}.advisor-edit-form input[type=text],.advisor-edit-form textarea{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid var(--line);border-radius:8px;background:var(--surface,#fff);color:inherit;font:inherit}.advisor-edit-form textarea{resize:vertical;line-height:1.4}.advisor-check{display:flex!important;gap:8px;align-items:flex-start}.advisor-check>span{margin:0!important;color:inherit!important;font-weight:600!important}.advisor-form-actions{display:flex;justify-content:flex-end}.advisor-delete{margin-top:10px;background:#fff1f2;color:#b91c1c;border-color:#fecaca}.advisor-delete:disabled{opacity:.4}.add-advisor-card{padding:18px;margin-top:20px;max-width:760px}.add-advisor-card h2{margin-top:0}@media(max-width:800px){.advisor-admin-grid{grid-template-columns:1fr}.admin-toolbar{justify-content:flex-start;flex-wrap:wrap}.advisor-image-form{align-items:stretch;flex-direction:column}}
html[data-theme="dark"] .admin-notice{background:#143322;color:#bbf7d0;border-color:#166534}html[data-theme="dark"] .advisor-delete{background:#3a1f26;color:#fecaca}
</style>
@endsection
