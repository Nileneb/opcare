<?php

namespace App\Livewire\Auth;

use App\Domains\Identity\Actions\RegisterUser;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(RegisterUser $registerUser): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $registerUser->handle($validated['name'], $validated['email'], $validated['password']);

        Auth::login($user);
        session()->regenerate();

        // WHY(Track B, MFA-Pflicht): Neue Konten richten zuerst 2FA ein (Enrollment-Middleware erzwingt es).
        $this->redirect(route('two-factor.enroll'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
