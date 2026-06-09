<x-layouts.app title="Bewerbung ansehen">
    <section class="page-heading">
        <h1>{{ $application->full_name }}</h1>
        <p>Bewerbung für: {{ $application->position_applied }}</p>
    </section>

    <div class="panel">
        <dl class="detail-list">
            <dt>E-Mail</dt>
            <dd>{{ $application->email }}</dd>

            <dt>Telefon</dt>
            <dd>{{ $application->phone ?? 'Keine Angabe' }}</dd>

            <dt>Stelle</dt>
            <dd>{{ $application->position_applied }}</dd>

            <dt>Status</dt>
            <dd>{{ ucfirst($application->status) }}</dd>

            <dt>Lebenslauf</dt>
            <dd>
                @if ($application->cv_path)
                    <a href="{{ Storage::disk(config('opcare.media_disk'))->url($application->cv_path) }}" target="_blank">Herunterladen</a>
                @else
                    Keine Datei hinterlegt
                @endif
            </dd>

            <dt>Anschreiben</dt>
            <dd><pre class="preserve">{{ $application->cover_letter }}</pre></dd>
        </dl>

        <form method="POST" action="{{ route('hr.applications.updateStatus', $application) }}">
            @csrf
            @method('PATCH')

            <div class="field">
                <label for="status">Status aktualisieren</label>
                <select id="status" name="status">
                    @foreach (['new', 'reviewing', 'rejected', 'hired'] as $status)
                        <option value="{{ $status }}" @selected($application->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>

        @if ($application->status === 'hired')
            <div class="panel" style="margin-top:1rem; background:#ecfdf5; border:1px solid #d1fae5; color:#065f46;">
                Einladung wurde bereits verschickt, als der Status auf „Eingestellt" gesetzt wurde.
            </div>
        @endif
    </div>
</x-layouts.app>
