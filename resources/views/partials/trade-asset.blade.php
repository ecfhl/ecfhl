@php
    $contract = null;
    $player = $item;
    if (preg_match('/^(.*?)\s*\((1 Year|[234] Years|FA|MINORS?|TBD)\)$/i', $item, $match)) {
        $player = $match[1];
        $contract = strtoupper($match[2]);
    }
    $color = in_array($contract, ['MINOR','MINORS','TBD'], true) ? 'orange' : (in_array($contract, ['2 YEARS','3 YEARS','4 YEARS'], true) ? 'blue' : 'gray');
@endphp
{{ $player }}@if($contract) <span class="trade-contract trade-contract--{{ $color }}">{{ $contract }}</span>@endif
@once
<style>
.trade-contract{display:inline-block;vertical-align:middle;margin-left:.3rem;padding:.15rem .45rem;border-radius:.35rem;font-size:.68em;font-weight:700;line-height:1.45;letter-spacing:.025em;white-space:nowrap}
.trade-contract--gray{background:#e5e7eb;color:#374151}
.trade-contract--blue{background:#dbeafe;color:#1e40af}
.trade-contract--orange{background:#ffedd5;color:#9a3412}
</style>
@endonce
