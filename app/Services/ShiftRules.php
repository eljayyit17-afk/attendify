<?php

namespace App\Services;

use App\Data\ShiftWindow;
use App\Models\Schedule;
use Carbon\CarbonImmutable;

class ShiftRules
{
    public function clockInWindow(Schedule $schedule, CarbonImmutable $now): ?ShiftWindow
    {
        if (! $schedule->isConfigured()) {
            return null;
        }
        $local = $now->setTimezone($schedule->timezone);
        // Include tomorrow for shifts whose early clock-in window crosses midnight.
        foreach ([0, -1, 1] as $offset) {
            $date = $local->addDays($offset)->startOfDay();
            if (! in_array($date->isoWeekday(), $schedule->working_days ?? [], true)) {
                continue;
            }
            $start = CarbonImmutable::parse($date->toDateString().' '.$schedule->start_time, $schedule->timezone)->utc();
            $endDate = substr($schedule->end_time ?? '', 0, 5) <= substr($schedule->start_time ?? '', 0, 5) ? $date->addDay() : $date;
            $end = CarbonImmutable::parse($endDate->toDateString().' '.$schedule->end_time, $schedule->timezone)->utc();
            if ($now->greaterThanOrEqualTo($start->subHour()) && $now->lessThan($end)) {
                return new ShiftWindow($date->toDateString(), $start, $end);
            }
        }

        return null;
    }

    public function currentWorkDate(?Schedule $schedule, CarbonImmutable $now): string
    {
        $window = $schedule ? $this->clockInWindow($schedule, $now) : null;
        if ($window) {
            return $window->workDate;
        }
        $local = $now->setTimezone($schedule?->isConfigured() ? $schedule->timezone : 'Asia/Manila');
        if ($schedule?->isConfigured() && substr($schedule->end_time ?? '', 0, 5) < substr($schedule->start_time ?? '', 0, 5)
            && $local->format('H:i') < substr($schedule->end_time ?? '', 0, 5)) {
            return $local->subDay()->toDateString();
        }

        return $local->toDateString();
    }
}
