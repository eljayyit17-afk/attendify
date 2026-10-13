<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ShiftWindow
{
    public function __construct(public string $workDate, public CarbonImmutable $start, public CarbonImmutable $end) {}
}
