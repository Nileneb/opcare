<div class="auth-card">
    <h1>Anmelden</h1>
    <p class="sub">Willkommen zurück. Bitte melde dich an.</p>

    <form wire:submit="login">
        <div class="field">
            <label for="email">E-Mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="email" autofocus required />
            @error('email') <span class="err">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password">Passwort</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required />
            @error('password') <span class="err">{{ $message }}</span> @enderror
        </div>

        <div class="field" style="flex-direction:row;align-items:center;justify-content:space-between">
            <label style="display:flex;align-items:center;gap:8px;font-weight:var(--fw-regular)">
                <input type="checkbox" wire:model="remember" style="width:auto" /> Angemeldet bleiben
            </label>
            <a href="{{ route('password.request') }}" wire:navigate style="font-size:0.85em;color:var(--color-link);text-decoration:none">Passwort vergessen?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="width:100%" wire:loading.attr="disabled">
            <span wire:loading.remove>Anmelden</span>
            <span wire:loading>Anmelden…</span>
        </button>
    </form>

    <p class="auth-alt">Noch kein Konto? <a href="{{ route('register') }}" wire:navigate>Registrieren</a></p>

    <div class="auth-demo">
        <b>Demo-Zugang:</b> admin@opcare.local · Passwort <b>password</b>
    </div>
</div>
