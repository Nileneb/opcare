<div class="auth-card">
    <h1>Bestätigung erforderlich</h1>
    <p class="sub">Gib den 6-stelligen Code aus deiner Authenticator-App ein. Alternativ kannst du einen
        deiner Wiederherstellungs-Codes verwenden.</p>

    <form wire:submit="verify">
        <div class="field">
            <label for="code">Code</label>
            <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                   wire:model="code" autofocus required />
            @error('code') <span class="err">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="width:100%" wire:loading.attr="disabled">
            <span wire:loading.remove>Anmelden</span>
            <span wire:loading>Prüfe…</span>
        </button>
    </form>

    <p class="auth-alt"><a href="{{ route('login') }}" wire:navigate>Zurück zur Anmeldung</a></p>
</div>
