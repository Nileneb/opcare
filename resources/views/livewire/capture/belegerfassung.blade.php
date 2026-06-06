<div>
    @php($cls = fn ($a) => $a === 'green' ? 'green' : ($a === 'amber' ? 'amber' : 'gray'))
    <div class="page-head">
        <div><p class="kicker">Verwaltung · Finanzen</p><h1>Beleg-Capture (VLM)</h1>
            <p class="lead">Belegfoto hochladen → die KI liest Betrag/Datum/Lieferant aus und schlägt eine Buchung vor.
                Der Vorschlag ist nie bindend — gebucht wird erst nach deiner Bestätigung.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Beleg hochladen</h3></div>
        <form wire:submit="analysieren">
            <div class="field" style="max-width:480px">
                <label>Belegfoto (Rechnung/Quittung/Kassenbon)</label>
                <input type="file" wire:model="bild" accept="image/*" />
                @error('bild')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div wire:loading wire:target="bild" class="muted" style="margin-top:6px">Lade Bild …</div>
            <div wire:loading wire:target="analysieren" class="muted" style="margin-top:6px">Analysiere Beleg …</div>
            <button class="btn btn-primary btn-sm" style="margin-top:10px" wire:loading.attr="disabled" wire:target="analysieren">Analysieren</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Belege & Vorschläge</h3><span class="badge gray">{{ $analysen->count() }}</span></div>
        <table class="data-table">
            <thead><tr><th>Erfasst</th><th>Modell · Konfidenz</th><th>Ziel</th><th>Extraktion</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($analysen as $a)
                    @foreach ($a->vorschlaege as $v)
                        <tr>
                            <td>{{ $a->created_at?->format('d.m.Y H:i') }}<br><span class="muted">{{ $a->erfasser?->name }}</span></td>
                            <td><code>{{ $a->modell }}</code> @if ($v->konfidenz !== null)<span class="badge {{ $v->konfidenz >= 0.8 ? 'green' : 'amber' }}">{{ number_format($v->konfidenz * 100, 0) }} %</span>@endif</td>
                            <td>{{ $v->ziel_typ->label() }}</td>
                            <td class="muted">
                                @if ($v->ziel_felder['betrag'] ?? null)<b>{{ number_format((float) $v->ziel_felder['betrag'], 2, ',', '.') }} €</b> · @endif
                                {{ $v->ziel_felder['datum'] ?? '–' }} · {{ $v->ziel_felder['lieferant'] ?? '–' }}
                            </td>
                            <td>
                                <span class="badge {{ $cls($v->status->ampel()) }}">{{ $v->status->label() }}</span>
                                @if ($v->buchung_id)<br><span class="muted">Buchung #{{ $v->buchung_id }}</span>@endif
                            </td>
                            <td style="white-space:nowrap">
                                @if ($v->offen() && $v->ziel_typ->buchbar())
                                    <button class="btn btn-ghost btn-sm" wire:click="bestaetigenStart({{ $v->id }})">Buchen …</button>
                                @endif
                                @if ($v->offen())
                                    <button class="btn btn-ghost btn-sm" wire:click="verwerfen({{ $v->id }})" wire:confirm="Vorschlag verwerfen?">Verwerfen</button>
                                @endif
                            </td>
                        </tr>
                        @if ($confirmId === $v->id)
                            <tr>
                                <td colspan="6" style="background:var(--surface-cool, #f6f8fa)">
                                    <form wire:submit="bestaetigen" style="padding:8px 4px">
                                        <p class="kicker">Buchung bestätigen — Soll an Haben (Betrag/Datum aus dem Beleg vorbelegt)</p>
                                        <div class="form-row-2">
                                            <div class="field"><label>Soll-Konto</label>
                                                <select wire:model="c_soll"><option value="">– wählen –</option>@foreach ($konten as $k)<option value="{{ $k->id }}">{{ $k->nummer }} · {{ $k->name }}</option>@endforeach</select>
                                                @error('c_soll')<span class="err">{{ $message }}</span>@enderror
                                            </div>
                                            <div class="field"><label>Haben-Konto</label>
                                                <select wire:model="c_haben"><option value="">– wählen –</option>@foreach ($konten as $k)<option value="{{ $k->id }}">{{ $k->nummer }} · {{ $k->name }}</option>@endforeach</select>
                                                @error('c_haben')<span class="err">{{ $message }}</span>@enderror
                                            </div>
                                        </div>
                                        <div class="form-row-2">
                                            <div class="field"><label>Buchungstext</label><input type="text" wire:model="c_text" />@error('c_text')<span class="err">{{ $message }}</span>@enderror</div>
                                            <div class="field"><label>Datum</label><input type="date" wire:model="c_datum" />@error('c_datum')<span class="err">{{ $message }}</span>@enderror</div>
                                        </div>
                                        <button class="btn btn-primary btn-sm">Buchen</button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('confirmId', null)">Abbrechen</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @empty
                    <tr><td colspan="6"><p class="empty">Noch keine Belege erfasst.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
