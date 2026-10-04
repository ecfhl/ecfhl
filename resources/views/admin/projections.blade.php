@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Projection Weights</h1><p>Choose how much each source contributes to your projected fantasy points per game.</p></div></div>
<div class="shell projection-settings">
  @if(session('notice'))<div class="projection-notice" role="status">{{ session('notice') }}</div>@endif
  @if($errors->any())<div class="projection-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
  <form action="/admin/projections" method="post" class="card projection-form" id="projection-weights-form" data-stored='@json($weights)' data-defaults='@json(\App\Support\ProjectionMath::DEFAULT_WEIGHTS)'>
    @csrf
    <h2>Weight each source</h2>
    <p class="subtle">1 unit = 10%. Divide 10 units across the five sources; set a slider to 0 to leave it out.</p>
    @foreach($labels as $key => $label)
      @php($units = old('units.'.$key, old('weights.'.$key, $weights[$key]) / 10))
      <div class="projection-weight-row">
        <div class="projection-weight-heading"><label for="weight-{{ $key }}">{{ $label }}</label><output id="value-{{ $key }}" for="weight-{{ $key }}">{{ $units }} / 10 <small>{{ $units * 10 }}%</small></output></div>
        <small class="projection-source-note">{{ $key === 'fantrax' ? 'Frozen Fantrax FPts/GP baseline' : 'Actual fantasy points divided by games played' }}</small>
        <input class="projection-slider" id="weight-{{ $key }}" name="units[{{ $key }}]" type="range" min="0" max="10" step="0.5" list="projection-unit-ticks" value="{{ $units }}" aria-describedby="projection-total" aria-valuetext="{{ $units }} out of 10 units, {{ $units * 10 }} percent">
        <div class="projection-slider-scale" aria-hidden="true"><span>0</span><span>10 units</span></div>
        <small class="projection-adjustment" id="adjust-{{ $key }}"></small>
      </div>
    @endforeach
    <datalist id="projection-unit-ticks">@for($unit = 0; $unit <= 10; $unit++)<option value="{{ $unit }}"></option>@endfor</datalist>
    <div class="projection-total-row"><strong>Total</strong><output id="projection-total" aria-live="polite">10 / 10 units · 100%</output></div>
    <p id="projection-total-help" class="subtle" aria-live="polite"></p>
    <div class="projection-actions"><button type="button" class="button primary" id="projection-preview-button">Preview Top 10</button><button type="button" class="button" id="projection-reset">Reset to Defaults</button></div>
    <section class="projection-preview" id="projection-preview" hidden aria-labelledby="projection-preview-heading">
      <h2 id="projection-preview-heading">Top 10 Preview</h2>
      <p id="projection-preview-status" class="subtle" role="status"></p>
      <div id="projection-preview-results" hidden>
        <div class="projection-preview-scroll"><table class="projection-preview-table"><thead><tr><th>#</th><th>Player</th><th>MyProj/GP</th><th>Season/GP</th></tr></thead><tbody id="projection-preview-players"></tbody></table></div>
        <p class="subtle projection-note">Ranked by MyProj using the sliders above. Season/GP is actual season fantasy points per game. Previewing leaves the saved weights unchanged.</p>
      </div>
    </section>
    <div class="projection-actions"><button type="submit" class="button primary" id="projection-save" data-loading-text="Saving & recalculating…" disabled>Save & Recalculate</button></div>
    <p class="subtle projection-note">Saving updates {{ number_format($count) }} stored player projections immediately. These weights are used everywhere on the site and in the daily 4 a.m. Atlantic refresh. A source with no games played contributes zero.</p>
  </form>
</div>
<style>
.projection-settings{padding-bottom:32px}.projection-form{max-width:660px;margin:18px auto;padding:22px}.projection-form h2{margin:0 0 8px}.projection-weight-row{padding:14px 0;border-bottom:1px solid var(--line)}.projection-weight-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.projection-weight-heading label{font-weight:700;min-width:0}.projection-weight-heading output{flex-shrink:0;font-size:18px;font-weight:800;white-space:nowrap}.projection-weight-heading output small{display:inline-block;margin-left:7px;color:var(--muted);font-size:13px;font-weight:600}.projection-source-note{display:block;color:var(--muted);margin-top:4px;font-size:12px}.projection-slider{display:block;width:100%;height:36px;padding:0;margin:8px 0 0;accent-color:var(--accent,#0055a7);cursor:pointer}.projection-slider:focus-visible{outline:2px solid var(--accent,#0055a7);outline-offset:3px;border-radius:6px}.projection-slider-scale{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;line-height:1}.projection-total-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;font-size:18px}.projection-total-row output{font-weight:800;color:#15803d}.projection-total-row output.invalid{color:#dc2626}.projection-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:16px}.projection-actions button{cursor:pointer}.projection-actions button:disabled{opacity:.55;cursor:default}.projection-note{font-size:12px;line-height:1.6;margin:18px 0 0}.projection-notice,.projection-errors{padding:12px 16px;border-radius:9px;margin-top:18px}.projection-notice{background:#dcfce7;color:#166534}.projection-errors{background:#fee2e2;color:#991b1b}.projection-errors p{margin:4px 0}html[data-theme="dark"] .projection-total-row output{color:#4ade80}html[data-theme="dark"] .projection-total-row output.invalid{color:#f87171}@media(max-width:600px){.projection-form{padding:16px}.projection-weight-heading output{font-size:16px}.projection-total-row{font-size:16px}.projection-actions .primary{flex:1}}
.projection-adjustment{display:block;margin-top:8px;color:var(--accent,#0055a7);font-weight:600;font-size:12px}.projection-adjustment:empty{display:none}.projection-preview{margin-top:22px;padding-top:20px;border-top:1px solid var(--line)}.projection-preview[hidden],.projection-preview [hidden]{display:none}.projection-preview h2{font-size:21px}.projection-preview-scroll{overflow-x:auto}.projection-preview-table{width:100%;border-collapse:collapse;font-size:13px}.projection-preview-table th,.projection-preview-table td{padding:10px 6px;border-bottom:1px solid var(--line);text-align:left}.projection-preview-table th{font-size:11px;color:var(--muted);white-space:nowrap}.projection-preview-table th:nth-child(n+3),.projection-preview-score{text-align:right!important;font-variant-numeric:tabular-nums}.projection-preview-name{font-weight:700;min-width:120px}.projection-preview-name small{display:block;font-size:11px;color:var(--muted);font-weight:400;margin-top:4px}.projection-preview-score{font-weight:700;white-space:nowrap}@media(max-width:600px){.projection-preview-table{font-size:12px}.projection-preview-table th,.projection-preview-table td{padding:9px 4px}.projection-preview-name{min-width:110px}}
</style>
<script src="/projection-weights.js?v=1" defer></script>
@endsection
