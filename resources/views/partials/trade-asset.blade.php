@php
    $contract = null;
    $player = $item;
    if (preg_match('/^(.*?)\s*\((1 Year|[234] Years|FA|MINORS?|TBD)\)$/i', $item, $match)) {
        $player = $match[1];
        $contract = strtoupper($match[2]);
    }

    $isPick = false;
    // Display draft picks as: 1st Round (2026) [Original Owner Team Name].
    // Omit the owner when the team trading the pick is its original owner.
    if (preg_match('/^(\d{4})\s+Draft\s+Pick\s+Round\s+(\d+)(?:\s+Pick\s+\d+)?(?:\s*\(([^)]+)\))?$/i', trim($player), $pick)) {
        $isPick = true;
        $year = $pick[1];
        $round = (int) $pick[2];
        $owner = isset($pick[3]) ? trim($pick[3]) : null;
        $sender = trim((string)($senderTeam ?? ''));
        if ($owner && $sender !== '' && strcasecmp($owner, $sender) === 0) $owner = null;
        $mod100 = $round % 100;
        $suffix = ($mod100 >= 11 && $mod100 <= 13) ? 'th' : match ($round % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
        $player = $round.$suffix.' Round ('.$year.')'.($owner ? ' ['.$owner.']' : '');
    }

    $fantraxUrl = (!$isPick && !$contract && !empty($season))
        ? \App\Support\TradeContracts::fantraxSearchUrl($season, $player)
        : null;
    $color = in_array($contract, ['MINOR','MINORS','TBD'], true) ? 'orange' : (in_array($contract, ['2 YEARS','3 YEARS','4 YEARS'], true) ? 'blue' : 'gray');
@endphp
@if($fantraxUrl)<a class="trade-missing-contract" href="{{ $fantraxUrl }}" target="_blank" rel="noopener" title="Contract missing — search this player in the {{ $season }} Fantrax league">{{ $player }}</a>@else{{ $player }}@endif@if($contract) <span class="trade-contract trade-contract--{{ $color }}">{{ $contract }}</span>@endif
@once
<style>
.trade-contract{display:inline-block;vertical-align:middle;margin-left:.3rem;padding:.15rem .45rem;border-radius:.35rem;font-size:.68em;font-weight:700;line-height:1.45;letter-spacing:.025em;white-space:nowrap}
.trade-contract--gray{background:#e5e7eb;color:#374151}
.trade-contract--blue{background:#dbeafe;color:#1e40af}
.trade-contract--orange{background:#ffedd5;color:#9a3412}
.trade-missing-contract{text-decoration:underline;text-decoration-style:dotted;text-underline-offset:3px;cursor:pointer}
</style>
@endonce
