<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class FantasyDay
{
    public const TIMEZONE = 'America/Vancouver';

    public function today(?DateTimeInterface $instant = null): CarbonImmutable
    {
        return ($instant ? CarbonImmutable::instance($instant) : CarbonImmutable::now(self::TIMEZONE))
            ->setTimezone(self::TIMEZONE)->startOfDay();
    }

    public function parse(string $date): CarbonImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) throw new InvalidArgumentException('Use date format YYYY-MM-DD.');
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
        if (!$day || $day->toDateString() !== $date) throw new InvalidArgumentException('Invalid fantasy date.');
        return $day;
    }

    public function dates(?DateTimeInterface $instant = null): array
    {
        $today = $this->today($instant);
        return ['yesterday'=>$today->subDay()->toDateString(), 'today'=>$today->toDateString(), 'tomorrow'=>$today->addDay()->toDateString()];
    }
}
