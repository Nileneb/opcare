<?php

namespace App\Livewire\Admin;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public function switchTo(int $tenantId): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        abort_unless(Tenant::aktiv()->whereKey($tenantId)->exists(), 404);
        session(['active_tenant_id' => $tenantId]);
        $this->redirect(route('overview'), navigate: false);
    }

    public function render()
    {
        return view('livewire.admin.tenant-switcher', [
            'tenants' => auth()->user()->isSuperAdmin() ? Tenant::aktiv()->orderBy('name')->get() : collect(),
            'current' => app(CurrentTenant::class)->id(),
        ]);
    }
}
