<?php
namespace App\Livewire\Admin;

use App\Domains\Identity\Actions\{AssignRole, CreateUser};
use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Users extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'pflegehilfskraft';

    public function mount(): void { $this->authorize('viewAny', User::class); }

    public function save(CreateUser $create): void
    {
        $this->authorize('create', User::class);
        $data = $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'exists:roles,name'],
        ]);
        $create->handle(new AdminUserData(...$data));
        $this->reset('name', 'email', 'password');
        session()->flash('status', 'Mitarbeitende:r angelegt.');
    }

    public function setRole(int $userId, string $role, AssignRole $assign): void
    {
        $this->authorize('update', User::find($userId));
        $assign->handle(User::findOrFail($userId), $role);
        session()->flash('status', 'Rolle aktualisiert.');
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::where('tenant_id', auth()->user()->tenant_id)->with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
