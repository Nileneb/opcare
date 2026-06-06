<div class="auth-card">
    @if ($confirmed)
        <h1>Wiederherstellungs-Codes</h1>
        <p class="sub">Bewahre diese Codes sicher auf. Jeder Code funktioniert einmalig, falls du keinen
            Zugriff auf deine Authenticator-App hast.</p>

        <ul class="recovery-codes" style="list-style:none;padding:0;margin:16px 0;display:grid;grid-template-columns:1fr 1fr;gap:8px">
            @foreach ($recoveryCodes as $rc)
                <li style="font-family:monospace;background:var(--color-surface-2,#f3f4f6);padding:8px;border-radius:6px;text-align:center">{{ $rc }}</li>
            @endforeach
        </ul>

        <button type="button" wire:click="finish" class="btn btn-primary btn-block" style="width:100%">
            Codes gesichert — weiter zur App
        </button>
    @else
        <h1>Zwei-Faktor-Authentifizierung einrichten</h1>
        <p class="sub">Pflicht für alle Konten. Scanne den QR-Code mit einer Authenticator-App
            (z. B. Google Authenticator, Aegis) und gib den angezeigten 6-stelligen Code ein.</p>

        <div style="display:flex;justify-content:center;margin:16px 0">{!! $qrSvg !!}</div>

        <p class="sub" style="text-align:center">Manuell: <code style="font-family:monospace">{{ $secret }}</code></p>

        <form wire:submit="confirm">
            <div class="field">
                <label for="code">Code aus der App</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       wire:model="code" maxlength="6" autofocus required />
                @error('code') <span class="err">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="width:100%" wire:loading.attr="disabled">
                <span wire:loading.remove>Aktivieren</span>
                <span wire:loading>Prüfe…</span>
            </button>
        </form>
    @endif
</div>
