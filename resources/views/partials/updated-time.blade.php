@php
    $updatedAt = $value ? \Carbon\CarbonImmutable::parse($value)->setTimezone('America/Halifax') : null;
    $updatedNow = \Carbon\CarbonImmutable::now('America/Halifax');
    $updatedSeconds = $updatedAt ? max(0, (int) floor($updatedAt->diffInSeconds($updatedNow))) : null;

    if ($updatedAt === null) {
        $updatedText = null;
    } elseif ($updatedSeconds < 60) {
        $updatedText = $updatedSeconds === 1 ? '1 second ago' : $updatedSeconds.' seconds ago';
    } elseif ($updatedSeconds < 3600) {
        $updatedMinutes = (int) floor($updatedSeconds / 60);
        $updatedText = $updatedMinutes === 1 ? '1 minute ago' : $updatedMinutes.' minutes ago';
    } else {
        $updatedText = $updatedAt->format('M j, Y · g:i:s a T');
    }
@endphp
@if($updatedText){{ $updatedText }}@endif
