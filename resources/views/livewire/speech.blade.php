<div>
    <div class="page-head">
        <div>
            <p class="kicker">Human-in-the-Loop</p>
            <h1>Spracherfassung</h1>
            <p class="lead">Sprachnotiz → lokale Transkription (Whisper) → KI-Strukturierung (Ollama) → menschliche Freigabe. Audio wird nach der Transkription gelöscht.</p>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Neue Sprachnotiz</h3></div>
        <p class="muted" style="margin-top:0">Im Demo-Modus (<code>SPEECH_FAKE=true</code>) wird die Aufnahme simuliert und die Pipeline läuft sofort durch — ohne echten Whisper/Ollama-Dienst.</p>
        <form wire:submit="startDemo">
            <div class="form-row">
                <div class="field"><label>Bewohner:in</label>
                    <select wire:model="resident_id"><option value="">— wählen —</option>
                        @foreach ($residents as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                    </select>@error('resident_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Kontext (Lebensbereich)</label>
                    <select wire:model="kontext">
                        @foreach ($areas as $a)<option value="{{ $a['key'] }}">{{ $a['name'] }}</option>@endforeach
                        <option value="bericht">Bericht</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove>🎙 Demo-Aufnahme starten</span>
                <span wire:loading>Verarbeite…</span>
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Transkriptions-Jobs</h3></div>
        @forelse ($jobs as $job)
            @php
                $badge = match ($job->status->value) {
                    'review' => 'amber', 'done' => 'green', 'failed' => 'red', default => 'gray',
                };
            @endphp
            <div class="chip" style="align-items:flex-start">
                <div style="flex:1">
                    <b>{{ $job->resident?->name ?? '—' }}</b> · {{ $job->kontext }}
                    <span class="badge {{ $badge }}" style="margin-left:8px">{{ $job->status->value }}</span>
                    @if ($job->rohtranskript)<br><span class="muted">„{{ $job->rohtranskript }}"</span>@endif
                    @if ($job->status === \App\Domains\Speech\Enums\TranscriptionStatus::Review && $job->sis_vorschlag)
                        <br><span class="muted">Vorschlag:
                            @foreach ($job->sis_vorschlag['felder'] ?? [] as $f)<span class="badge gray" style="margin-right:4px">{{ $f['themenfeld'] }}</span>@endforeach
                        </span>
                    @endif
                </div>
                @if ($job->status === \App\Domains\Speech\Enums\TranscriptionStatus::Review)
                    <button class="btn btn-primary btn-sm" wire:click="approve({{ $job->id }})">✓ Freigeben → SIS</button>
                @elseif ($job->status === \App\Domains\Speech\Enums\TranscriptionStatus::Done)
                    <span class="badge green">übernommen</span>
                @endif
            </div>
        @empty
            <p class="empty">Noch keine Sprachnotizen. Starte oben eine Demo-Aufnahme.</p>
        @endforelse
    </div>
</div>
