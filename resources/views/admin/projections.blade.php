@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Projection Weights</h1><p>Choose how much each source contributes to your projected fantasy points per game.</p></div></div>
<div class="shell projection-settings">
  @if(session('notice'))<div class="projection-notice" role="status">{{ session('notice') }}</div>@endif
  @if($errors->any())<div class="projection-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
  <form action="/admin/projections" method="post" class="card projection-form" id="projection-weights-form">
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
      </div>
    @endforeach
    <datalist id="projection-unit-ticks">@for($unit = 0; $unit <= 10; $unit++)<option value="{{ $unit }}"></option>@endfor</datalist>
    <div class="projection-total-row"><strong>Total</strong><output id="projection-total" aria-live="polite">10 / 10 units · 100%</output></div>
    <p id="projection-total-help" class="subtle" aria-live="polite"></p>
    <div class="projection-actions"><button type="submit" class="button primary" id="projection-save" data-loading-text="Saving & recalculating…">Save & Recalculate</button><button type="button" class="button" id="projection-reset">Reset to Defaults</button></div>
    <p class="subtle projection-note">Saving updates {{ number_format($count) }} stored player projections immediately. These weights are used everywhere on the site and in the daily 4 a.m. Atlantic refresh. A source with no games played contributes zero.</p>
  </form>
</div>
<style>
.projection-settings{padding-bottom:32px}.projection-form{max-width:660px;margin:18px auto;padding:22px}.projection-form h2{margin:0 0 8px}.projection-weight-row{padding:14px 0;border-bottom:1px solid var(--line)}.projection-weight-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.projection-weight-heading label{font-weight:700;min-width:0}.projection-weight-heading output{flex-shrink:0;font-size:18px;font-weight:800;white-space:nowrap}.projection-weight-heading output small{display:inline-block;margin-left:7px;color:var(--muted);font-size:13px;font-weight:600}.projection-source-note{display:block;color:var(--muted);margin-top:4px;font-size:12px}.projection-slider{display:block;width:100%;height:36px;padding:0;margin:8px 0 0;accent-color:var(--accent,#0055a7);cursor:pointer}.projection-slider:focus-visible{outline:2px solid var(--accent,#0055a7);outline-offset:3px;border-radius:6px}.projection-slider-scale{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;line-height:1}.projection-total-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;font-size:18px}.projection-total-row output{font-weight:800;color:#15803d}.projection-total-row output.invalid{color:#dc2626}.projection-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:16px}.projection-actions button{cursor:pointer}.projection-actions button:disabled{opacity:.55;cursor:default}.projection-note{font-size:12px;line-height:1.6;margin:18px 0 0}.projection-notice,.projection-errors{padding:12px 16px;border-radius:9px;margin-top:18px}.projection-notice{background:#dcfce7;color:#166534}.projection-errors{background:#fee2e2;color:#991b1b}.projection-errors p{margin:4px 0}html[data-theme="dark"] .projection-total-row output{color:#4ade80}html[data-theme="dark"] .projection-total-row output.invalid{color:#f87171}@media(max-width:600px){.projection-form{padding:16px}.projection-weight-heading output{font-size:16px}.projection-total-row{font-size:16px}.projection-actions .primary{flex:1}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const form=document.getElementById('projection-weights-form'),inputs=[...form.querySelectorAll('input[type="range"]')],save=document.getElementById('projection-save'),total=document.getElementById('projection-total'),help=document.getElementById('projection-total-help');
  const stored=@json($weights),defaults=@json(\App\Support\ProjectionMath::DEFAULT_WEIGHTS);
  const key=input=>input.id.slice(7);
  const update=()=>{
    const sum=inputs.reduce((value,input)=>value+Math.round(Number(input.value)*10),0),valid=inputs.every(input=>input.checkValidity())&&sum===100;
    inputs.forEach(input=>{
      const units=Number(input.value),percent=Math.round(units*10),output=document.getElementById('value-'+key(input));
      output.textContent=units+' / 10 · '+percent+'%';input.setAttribute('aria-valuetext',units+' out of 10 units, '+percent+' percent');
    });
    total.textContent=(sum/10).toLocaleString(undefined,{maximumFractionDigits:1})+' / 10 units · '+sum+'%';total.classList.toggle('invalid',!valid);
    const changed=inputs.some(input=>Math.round(Number(input.value)*10)!==Number(stored[key(input)]));
    save.disabled=!valid||!changed;help.textContent=!valid?'Adjust the sliders to total exactly 10 units (100%).':!changed?'Current weights are saved.':'Ready to save and recalculate.';
  };
  inputs.forEach(input=>input.addEventListener('input',update));
  document.getElementById('projection-reset').addEventListener('click',()=>{inputs.forEach(input=>input.value=defaults[key(input)]/10);update();});
  form.addEventListener('submit',event=>{if(save.disabled){event.preventDefault();return;}form.querySelectorAll('button[type="button"]').forEach(button=>button.disabled=true);});
  window.addEventListener('pageshow',event=>{if(event.persisted){document.getElementById('projection-reset').disabled=false;update();}});
  update();
});
</script>
@endsection
