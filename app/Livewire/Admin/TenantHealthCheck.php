<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\Tenancy\TenantHealthService;
use Livewire\Component;
use Throwable;

final class TenantHealthCheck extends Component
{
    public Tenant $tenant;

    public ?array $results = null;

    public bool $loading = false;

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function runCheck(TenantHealthService $healthService)
    {
        $this->loading = true;
        try {
            $this->results = $healthService->runAll($this->tenant);
        } catch (Throwable $e) {
            $this->results = [
                'error' => $e->getMessage(),
            ];
        }
        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.admin.tenant-health-check');
    }
}
