<?php

namespace App\Livewire\Employee;

use App\Services\LeaveManagement;
use Livewire\WithPagination;

class LeaveWorkspace extends PortalComponent
{
    use WithPagination;

    public string $type = 'VL';

    public string $from = '';

    public string $to = '';

    public string $reason = '';

    public string $notice = '';

    public bool $showRequest = false;

    public function submit(LeaveManagement $service): void
    {
        $service->request($this->type, $this->from, $this->to, $this->reason);
        $this->showRequest = false;
        $this->reset(['from', 'to', 'reason']);
        $this->resetPage();
        $this->notice = 'Leave request submitted for review.';
    }

    public function cancelRequest(int $id, LeaveManagement $service): void
    {
        $service->cancel($id);
        $this->notice = 'Leave request cancelled.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return ['requests' => $this->employee()->leaveRequests()->with('approver:id,name')->latest('id')->paginate(15)];
    }
}
