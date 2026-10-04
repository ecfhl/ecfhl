@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Administration</div><h1>Projection Weights</h1><p>Choose how much each source contributes to your projected fantasy points per game.</p></div></div>
<div class="shell projection-settings">
  @if(session('notice'))<div class="projection-notice" role="status">{{ session('notice') }}</div>@endif
  @if($errors->any())<div class="projection-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
  <form action="/admin/projections" method="post" class="card projection-form" id="projection-weights-form">
    @csrf
    <h2>Weight each source</h2>
    <p class="subtle">Set a source to 0% to leave it out. The five percentages must total 100%.</p>
    @foreach($labels as $key => $label)
      <div class="projection-weight-row">
        <label for="weight-{{ $key }}">{{ $label }}<small>{{ $key === 'fantrax' ? 'Frozen Fantrax FPts/GP baseline' : 'Actual fantasy points divided by games played' }}</small></label>
        <div class="projection-input"><input id="weight-{{ $key }}" name="weights[{{ $key }}]" type="number" inputmode="decimal" min="0" max="100" step="0.01" value="{{ old('weights.'.$key, $weights[$key]) }}" required aria-describedby="projection-total"><span>%</span></div>
      </div>
    @endforeach
    <div class="projection-total-row"><strong>Total</strong><output id="projection-total" aria-live="polite">100%</output></div>
    <p id="projection-total-help" class="subtle" aria-live="polite"></p>
    <div class="projection-actions"><button type="submit" class="button primary" id="projection-save" data-loading-text="Saving & recalculating…">Save & Recalculate</button><button type="button" class="button" id="projection-reset">Reset to Defaults</button></div>
    <p class="subtle projection-note">Saving updates {{ number_format($count) }} stored player projections immediately. These weights are used everywhere on the site and in the daily 4 a.m. Atlantic refresh. A source with no games played contributes zero.</p>
  </form>
</div>
<style>
.projection-settings{padding-bottom:32px}.projection-form{max-width:660px;margin:18px auto;padding:22px}.projection-form h2{margin:0 0 8px}.projection-weight-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid var(--line)}.projection-weight-row label{font-weight:700;min-width:0}.projection-weight-row small{display:block;font-weight:400;color:var(--muted);margin-top:4px;font-size:12px}.projection-input{display:flex;align-items:center;gap:7px;flex-shrink:0}.projection-input input{width:94px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--text);padding:10px;font-size:18px;font-weight:700;text-align:right}.projection-total-row{display:flex;justify-content:space-between;align-items:center;margin-top:18px;font-size:20px}.projection-total-row output{font-weight:800;color:#15803d}.projection-total-row output.invalid{color:#dc2626}.projection-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:16px}.projection-actions button{cursor:pointer}.projection-actions button:disabled{opacity:.55;cursor:default}.projection-note{font-size:12px;line-height:1.6;margin:18px 0 0}.projection-notice,.projection-errors{padding:12px 16px;border-radius:9px;margin-top:18px}.projection-notice{background:#dcfce7;color:#166534}.projection-errors{background:#fee2e2;color:#991b1b}.projection-errors p{margin:4px 0}html[data-theme="dark"] .projection-total-row output{color:#4ade80}html[data-theme="dark"] .projection-total-row output.invalid{color:#f87171}@media(max-width:600px){.projection-form{padding:16px}.projection-weight-row{gap:10px}.projection-input input{width:80px}.projection-weight-row small{max-width:200px}.projection-actions .primary{flex:1}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const form=document.getElementById('projection-weights-form'),inputs=[...form.querySelectorAll('input[type="number"]')],save=document.getElementById('projection-save'),total=document.getElementById('projection-total'),help=document.getElementById('projection-total-help');
  const stored=@json($weights),defaults=@json(\App\Support\ProjectionMath::DEFAULT_WEIGHTS);
  const key=input=>input.id.slice(7);
  const update=()=>{
    const sum=inputs.reduce((value,input)=>value+Math.round(Number(input.value)*100),0),valid=inputs.every(input=>input.checkValidity())&&sum===10000;
    total.textContent=(sum/100).toLocaleString(undefined,{maximumFractionDigits:2})+'%';total.classList.toggle('invalid',!valid);
    const changed=inputs.some(input=>Number(input.value)!==Number(stored[key(input)]));
    save.disabled=!valid||!changed;help.textContent=!valid?'Adjust the weights to total exactly 100%.':!changed?'Current weights are saved.':'Ready to save and recalculate.';
  };
  inputs.forEach(input=>input.addEventListener('input',update));
  document.getElementById('projection-reset').addEventListener('click',()=>{inputs.forEach(input=>input.value=defaults[key(input)]);update();});
  form.addEventListener('submit',event=>{if(save.disabled){event.preventDefault();return;}form.querySelectorAll('button[type="button"]').forEach(button=>button.disabled=true);});
  window.addEventListener('pageshow',event=>{if(event.persisted){document.getElementById('projection-reset').disabled=false;update();}});
  update();
});
</script>
@endsection
