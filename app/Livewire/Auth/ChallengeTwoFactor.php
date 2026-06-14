<?php

namespace App\Livewire\Auth;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\TwoFactorAuthenticator;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * TOTP-Login-Challenge (Track B): zweiter Faktor nach erfolgreicher Passwortprüfung. Der Benutzer ist
 * noch NICHT authentifiziert (Session-Hand-off aus der Login-Komponente) — erst ein gültiger TOTP- oder
 * Recovery-Code führt Auth::login aus.
 */
#[Layout('layouts.guest')]
class ChallengeTwoFactor extends Component
{
    public string $code = '';

    public function mount(): void
    {
        if (! session('mfa.pending_id')) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if (config('app.disable_two_factor')) {
            session()->forget(['mfa.pending_id', 'mfa.remember']);
            $this->redirect(route('overview'), navigate: true);
        }
    }

    public function verify(TwoFactorAuthenticator $tfa): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::find(session('mfa.pending_id'));

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            session()->forget(['mfa.pending_id', 'mfa.remember']);
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $code = trim($this->code);
        $ok = $tfa->verify((string) $user->two_factor_secret, $code) || $this->consumeRecoveryCode($user, $code);

        if (! $ok) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['code' => __('Der eingegebene Code ist ungültig.')]);
        }

        RateLimiter::clear($this->throttleKey());

        Auth::login($user, (bool) session('mfa.remember'));
        session()->forget(['mfa.pending_id', 'mfa.remember']);
        session()->regenerate();

        $this->redirect(route('overview'), navigate: true);
    }

    /** Recovery-Code (Einmal-Nutzung) prüfen + verbrauchen. */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        if ($code === '' || ! in_array($code, $codes, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$code])),
        ])->save();

        return true;
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate('2fa|'.session('mfa.pending_id').'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.challenge-two-factor');
    }
}
