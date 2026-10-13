<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceAnalytics
{
    public const STATUSES = ['Present', 'Late', 'Absent', 'VL', 'SL', 'Holiday', 'Rest day', 'Not due', 'Unscheduled'];

    public function authorize(): void
    {
        abort_unless(Auth::id() !== null, 403);
        $actor = User::findOrFail(Auth::id());
        Gate::forUser($actor)->authorize('viewAny', User::class);
        abort_if($actor->requires_password_setup, 403);
    }

    /** @param array<string, mixed> $filters */
    public function validate(array $filters): void
    {
        $this->authorize();
        Validator::make($filters, [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employeeId' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'employee')],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'grouping' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
        ])->validate();
        if (CarbonImmutable::parse($filters['from'])->diffInDays(CarbonImmutable::parse($filters['to'])) > 365) {
            throw ValidationException::withMessages(['to' => 'Choose at most 366 work dates.']);
        }
    }

    /** @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function rows(array $filters): LazyCollection
    {
        $filters = array_replace(['employeeId' => '', 'department' => '', 'status' => '', 'grouping' => 'daily'], $filters);
        $this->validate($filters);

        return app(WorkdayReport::class)->rows($filters['from'], $filters['to'],
            empty($filters['employeeId']) ? null : (int) $filters['employeeId'], $filters['department'] ?: null, 366)
            ->filter(fn ($row) => empty($filters['status']) || $row['status'] === $filters['status'])->values();
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summarize(array $filters, int $page = 1, int $perPage = 50, bool $includeDetails = false): array
    {
        $filters = array_replace(['employeeId' => '', 'department' => '', 'status' => '', 'grouping' => 'daily'], $filters);
        $source = $this->rows($filters);
        $counts = [];
        $periods = [];
        $departments = [];
        $details = collect();
        $total = 0;
        $seconds = 0;
        $incomplete = 0;
        $employeeIds = [];
        for ($date = CarbonImmutable::parse($filters['from']); $date->lte(CarbonImmutable::parse($filters['to'])); $date = $date->addDay()) {
            $key = match ($filters['grouping']) {
                'weekly' => $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), 'monthly' => $date->format('Y-m'), default => $date->toDateString()
            };
            $periods[$key] = [];
        }
        foreach ($source as $row) {
            $total++;
            $employeeIds[$row['user_id']] = true;
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
            $date = CarbonImmutable::parse($row['date'], 'UTC');
            $key = match ($filters['grouping']) {
                'weekly' => $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                'monthly' => $date->format('Y-m'),
                default => $row['date'],
            };
            $periods[$key][$row['status']] = ($periods[$key][$row['status']] ?? 0) + 1;
            $department = trim($row['department']) ?: 'Unassigned';
            $departments[$department][$row['status']] = ($departments[$department][$row['status']] ?? 0) + 1;
            if ($row['session']) {
                if ($row['session']->clock_out_at) {
                    $seconds += $row['session']->durationSeconds();
                } else {
                    $incomplete++;
                }
            }
            if (($includeDetails && $total <= 500) || (! $includeDetails && $total > ($page - 1) * $perPage && $total <= $page * $perPage)) {
                $details->push($row);
            }
        }
        ksort($periods);
        ksort($departments);

        return ['counts' => $counts, 'periods' => $periods, 'departmentCounts' => $departments,
            'details' => $details, 'total' => $total, 'finalizedSeconds' => $seconds,
            'detailsTruncated' => $includeDetails && $total > 500,
            'incomplete' => $incomplete, 'employeeCount' => count($employeeIds),
            'attendanceRate' => $this->attendanceRate($counts),
            'onTimeRate' => (($counts['Present'] ?? 0) + ($counts['Late'] ?? 0)) > 0
                ? round(100 * ($counts['Present'] ?? 0) / (($counts['Present'] ?? 0) + ($counts['Late'] ?? 0)), 1) : null];
    }

    /** @param array<string, int> $counts */
    public function attendanceRate(array $counts): ?float
    {
        $attended = ($counts['Present'] ?? 0) + ($counts['Late'] ?? 0);
        $denominator = $attended + ($counts['Absent'] ?? 0);

        return $denominator > 0 ? round(100 * $attended / $denominator, 1) : null;
    }
}
