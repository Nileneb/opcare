<?php

namespace App\Livewire\Admin;

use App\Domains\Identity\Actions\CreateTenant;
use App\Domains\Identity\Data\TenantData;
use App\Domains\Identity\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Tenants extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $traeger = '';

    public string $ik_nummer = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Tenant::class);
    }

    public function save(CreateTenant $create): void
    {
        $this->authorize('create', Tenant::class);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'unique:tenants,slug'],
            'traeger' => ['nullable', 'string'],
            'ik_nummer' => ['nullable', 'string'],
        ]);
        $create->handle(new TenantData(...$data));
        $this->reset('name', 'slug', 'traeger', 'ik_nummer');
        session()->flash('status', 'Einrichtung angelegt.');
    }

    public function render()
    {
        return view('livewire.admin.tenants', ['tenants' => Tenant::orderBy('name')->get()]);
    }
}
