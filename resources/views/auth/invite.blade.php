<x-layouts.guest title="Einladung annehmen">
    <div class="auth-card">
        <h1>Du wurdest eingeladen</h1>
        <p class="sub">Erstelle hier dein Konto für {{ $invitation->email }}.</p>
        <p style="margin-top:0.5rem; color:var(--color-fg-muted);">
            Eingeladen von {{ $invitation->invitedBy->name ?? 'deiner Personalabteilung' }}.<br />
            Dieser Link ist gültig bis {{ $invitation->expires_at->format('d.m.Y H:i') }}.
        </p>

        @if ($errors->any())
            <div style="margin-bottom:16px;color:#b91c1c;">
                <strong>Fehler:</strong>
                <ul style="margin:8px 0 0;padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
            @csrf

            <div class="field">
                <label>E-Mail</label>
                <input type="email" value="{{ $invitation->email }}" readonly />
            </div>

            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required />
                @error('name') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password">Passwort</label>
                <input id="password" name="password" type="password" required />
                @error('password') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Passwort wiederholen</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required />
            </div>

            <button type="submit" class="btn btn-primary btn-block">Konto erstellen</button>
        </form>
    </div>
</x-layouts.guest>
