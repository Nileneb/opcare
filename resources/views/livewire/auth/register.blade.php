<div class="auth-card">
    <h1>Konto erstellen</h1>
    <p class="sub">Neuen Pflege-Zugang anlegen.</p>

    <form wire:submit="register">
        <div class="field">
            <label for="name">Name</label>
            <input id="name" type="text" wire:model="name" autocomplete="name" autofocus required />
            @error('name') <span class="err">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="email">E-Mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="email" required />
            @error('email') <span class="err">{{ $message }}</span> @enderror
        </div>
        <div class="form-row">
            <div class="field">
                <label for="password">Passwort</label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password" required />
                @error('password') <span class="err">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Passwort bestätigen</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required />
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%" wire:loading.attr="disabled">
            <span wire:loading.remove>Registrieren</span>
            <span wire:loading>Registrieren…</span>
        </button>
    </form>

    <p class="auth-alt">Bereits ein Konto? <a href="{{ route('login') }}" wire:navigate>Anmelden</a></p>
</div>
