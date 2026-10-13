<?php

namespace App\Livewire\Admin;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveManagement;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveReview extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    #[Locked]
    public ?int $reviewId = null;

    public bool $showReview = false;

    public string $remarks = '';

    public string $notice = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', User::class);
        abort_if(auth()->user()?->requires_password_setup, 403);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function review(int $id): void
    {
        Gate::authorize('decide', LeaveRequest::findOrFail($id));
        $this->reviewId = $id;
        $this->remarks = '';
        $this->resetValidation();
        $this->showReview = true;
    }

    public function decide(string $decision, LeaveManagement $service): void
    {
        abort_unless($this->reviewId !== null, 422);
        $service->decide($this->reviewId, $decision, $this->remarks);
        $this->showReview = false;
        $this->notice = 'Leave request '.$decision.'.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return ['requests' => LeaveRequest::with(['user:id,name', 'approver:id,name'])->when(in_array($this->status, ['pending', 'approved', 'rejected', 'cancelled'], true), fn ($q) => $q->where('status', $this->status))->latest('id')->paginate(15),
            'reviewing' => $this->showReview && $this->reviewId ? LeaveRequest::with('user:id,name')->findOrFail($this->reviewId) : null];
    }
}
