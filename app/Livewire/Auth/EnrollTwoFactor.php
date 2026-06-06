<?php

namespace App\Livewire\Auth;

use App\Domains\Identity\Support\TwoFactorAuthenticator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pflicht-Enrollment (Track B): zeigt QR-Code + Secret, verifiziert den ersten TOTP-Code, persistiert
 * Secret + Recovery-Codes und schließt das Enrollment ab. Der Benutzer ist hier bereits eingeloggt,
 * wird aber von der Enrollment-Middleware bis zum Abschluss auf dieser Seite gehalten.
 */
#[Layout('layouts.guest')]
class EnrollTwoFactor extends Component
{
    public string $code = '';

    public string $secret = '';

    public ?string $qrSvg = null;

    /** @var array<int, string>|null */
    public ?array $recoveryCodes = null;

    public bool $confirmed = false;

    public function mount(TwoFactorAuthenticator $tfa): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }
        if ($user->hasTwoFactorEnabled()) {
            $this->redirect(route('overview'), navigate: true);

            return;
        }

        // Secret über Livewire-Requests stabil halten, bis das Enrollment bestätigt ist.
        $this->secret = session('mfa.enroll_secret') ?? tap(
            $tfa->generateSecret(),
            fn (string $s) => session(['mfa.enroll_secret' => $s]),
        );
        $this->qrSvg = $tfa->qrCodeSvg((string) $user->email, $this->secret);
    }

    public function confirm(TwoFactorAuthenticator $tfa): void
    {
        $user = Auth::user();

        if ($user === null || $user->hasTwoFactorEnabled()) {
            $this->redirect(route('overview'), navigate: true);

            return;
        }

        if (! $tfa->verify($this->secret, $this->code)) {
            throw ValidationException::withMessages(['code' => __('Der eingegebene Code ist ungültig.')]);
        }

        $this->recoveryCodes = $tfa->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => $this->recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('mfa.enroll_secret');
        $this->confirmed = true;
    }

    public function finish(): void
    {
        $this->redirect(route('overview'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.enroll-two-factor');
    }
}
