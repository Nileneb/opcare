<div class="auth-card">
    <h1>Neues Passwort</h1>
    <p class="sub">Wähle ein neues, sicheres Passwort.</p>

    <form wire:submit="resetPassword">
        <div class="field">
            <label for="email">E-Mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="email" required />
            @error('email') <span class="err">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="password">Neues Passwort</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" required />
            @error('password') <span class="err">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Passwort bestätigen</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required />
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Passwort zurücksetzen</button>
    </form>
</div>
