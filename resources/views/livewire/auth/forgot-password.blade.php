<div class="auth-card">
    <h1>Passwort vergessen</h1>
    <p class="sub">Wir senden dir einen Link zum Zurücksetzen.</p>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    <form wire:submit="sendReset">
        <div class="field">
            <label for="email">E-Mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="email" autofocus required />
            @error('email') <span class="err">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Reset-Link senden</button>
    </form>

    <p class="auth-alt"><a href="{{ route('login') }}" wire:navigate>Zurück zur Anmeldung</a></p>
</div>
