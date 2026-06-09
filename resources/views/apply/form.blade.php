<x-layouts.guest title="Bewerbung einreichen">
    <div class="auth-card">
        <h1>Bewerbung einreichen</h1>
        <p class="sub">Schicken Sie Ihre Bewerbung an unsere Personalabteilung.</p>

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

        <form method="POST" action="{{ route('apply.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label for="first_name">Vorname</label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required />
            </div>

            <div class="field">
                <label for="last_name">Nachname</label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required />
            </div>

            <div class="field">
                <label for="email">E-Mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required />
            </div>

            <div class="field">
                <label for="phone">Telefon</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone') }}" />
            </div>

            <div class="field">
                <label for="position_applied">Gewünschte Stelle</label>
                <input id="position_applied" name="position_applied" type="text" value="{{ old('position_applied') }}" required />
            </div>

            <div class="field">
                <label for="cover_letter">Anschreiben</label>
                <textarea id="cover_letter" name="cover_letter" rows="5">{{ old('cover_letter') }}</textarea>
            </div>

            <div class="field">
                <label for="cv">Lebenslauf (PDF, DOC, DOCX)</label>
                <input id="cv" name="cv" type="file" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" />
                <p id="cv-name" style="margin:0.5rem 0 0; color:var(--color-fg-muted); font-size:0.95rem;">Keine Datei ausgewählt.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Bewerbung absenden</button>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const input = document.getElementById('cv');
                    const label = document.getElementById('cv-name');

                    if (! input || ! label) {
                        return;
                    }

                    input.addEventListener('change', function () {
                        label.textContent = input.files?.length ? input.files[0].name : 'Keine Datei ausgewählt.';
                    });
                });
            </script>
        </form>
    </div>
</x-layouts.guest>
