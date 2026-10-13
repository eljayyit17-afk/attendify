<?php

namespace App\Livewire\Employee;

use App\Services\WorkdayReport;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

class WorkCalendar extends PortalComponent
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if ($this->month === '') {
            $this->month = CarbonImmutable::now('Asia/Manila')->format('Y-m');
        }
    }

    public function moveMonth(int $direction): void
    {
        $this->validate(['month' => 'required|date_format:Y-m']);
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonths($direction < 0 ? -1 : 1)->format('Y-m');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $this->validate(['month' => 'required|date_format:Y-m']);
        $first = CarbonImmutable::parse($this->month.'-01', 'UTC');

        return ['first' => $first, 'days' => app(WorkdayReport::class)->rows($first->toDateString(), $first->endOfMonth()->toDateString())];
    }
}
