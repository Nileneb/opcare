<?php

namespace App\Livewire\Admin;

use App\Domains\Identity\Models\Tenant;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public function switchTo(int $tenantId): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        abort_unless(Tenant::whereKey($tenantId)->exists(), 404);
        session(['active_tenant_id' => $tenantId]);
        $this->redirect(route('overview'), navigate: false);
    }

    public function render()
    {
        return view('livewire.admin.tenant-switcher', [
            'tenants' => auth()->user()->hasRole('super-admin') ? Tenant::aktiv()->orderBy('name')->get() : collect(),
            'current' => app(\App\Domains\Identity\Support\CurrentTenant::class)->id(),
        ]);
    }
}
