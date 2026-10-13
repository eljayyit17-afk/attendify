<?php

namespace App\Livewire\Employee;

use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class PersonalHistory extends PortalComponent
{
    use WithPagination;

    #[Url]
    public string $fromDate = '';

    #[Url]
    public string $toDate = '';

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->fromDate = '';
        $this->toDate = '';
        $this->resetValidation();
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $validator = Validator::make(['fromDate' => $this->fromDate, 'toDate' => $this->toDate], [
            'fromDate' => ['nullable', 'date_format:Y-m-d'],
            'toDate' => array_filter(['nullable', 'date_format:Y-m-d', $this->fromDate ? 'after_or_equal:fromDate' : null]),
        ]);
        $valid = $validator->passes();
        $this->setErrorBag($validator->errors());
        $query = $this->employee()->attendanceSessions();

        return ['records' => $query->when(! $valid, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($valid && $this->fromDate !== '', fn ($query) => $query->where('work_date', '>=', $this->fromDate))
            ->when($valid && $this->toDate !== '', fn ($query) => $query->where('work_date', '<=', $this->toDate))
            ->latest('work_date')->paginate(15)];
    }
}
