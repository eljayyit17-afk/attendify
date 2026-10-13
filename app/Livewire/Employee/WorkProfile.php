<?php

namespace App\Livewire\Employee;

class WorkProfile extends PortalComponent
{
    /** @return array<string, mixed> */
    public function with(): array
    {
        return ['employee' => $this->employee()];
    }
}
