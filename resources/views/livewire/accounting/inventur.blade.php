<div class="page">
    <div class="page-head">
        <div><p class="kicker">Verwaltung · Finanzen</p><h1>Inventur & Bestandsbewertung</h1>
            <p class="lead">Körperliche Bestandsaufnahme (§§ 240/241 HGB): Soll-Ist je Artikel, Differenzen werden gebucht, der Bestandswert (FIFO, § 256 HGB) wird eingefroren.</p></div>
        <div><span class="badge gray">Lagerwert aktuell: {{ number_format($bestandswert, 2, ',', '.') }} €</span></div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Neue Inventur starten</h3></div>
        <form wire:submit="starten" class="form-row-3" style="align-items:end">
            <div class="field"><label>Stichtag</label><input type="date" wire:model="neu_stichtag" />@error('neu_stichtag')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Abteilung (optional)</label>
                <select wire:model="neu_abteilung"><option value="">– ganzes Haus –</option>@foreach ($abteilungen as $abt)<option value="{{ $abt->value }}">{{ $abt->label() }}</option>@endforeach</select>
            </div>
            <div class="field"><button class="btn btn-primary btn-sm">Inventur starten</button></div>
        </form>
    </div>

    @forelse ($offene as $inv)
        <div class="card">
            <div class="card-head">
                <h3>Offene Inventur · Stichtag {{ $inv->stichtag->format('d.m.Y') }}</h3>
                <span class="badge amber">{{ $inv->abteilung?->label() ?? 'ganzes Haus' }}</span>
            </div>
            <table class="data-table">
                <thead><tr><th>Artikel</th><th style="text-align:right">Soll</th><th style="text-align:right">Ist zählen</th><th style="text-align:right">Differenz</th><th></th></tr></thead>
                <tbody>
                @foreach ($inv->positionen as $pos)
                    <tr>
                        <td><b>{{ $pos->artikel->name }}</b> <span class="muted">/ {{ $pos->artikel->einheit }}</span></td>
                        <td style="text-align:right">{{ number_format((float) $pos->soll_menge, 2, ',', '.') }}</td>
                        <td style="text-align:right">
                            <input type="number" step="0.01" style="max-width:110px" wire:model="ist.{{ $pos->id }}"
                                   placeholder="{{ $pos->gezaehlt() ? number_format((float) $pos->ist_menge, 2, ',', '.') : '—' }}" />
                        </td>
                        <td style="text-align:right">
                            @if ($pos->gezaehlt())
                                @php($d = $pos->differenzMenge())
                                <span class="badge {{ abs($d) < 0.005 ? 'green' : ($d < 0 ? 'red' : 'amber') }}">{{ $d > 0 ? '+' : '' }}{{ number_format($d, 2, ',', '.') }}</span>
                            @else
                                <span class="muted">nicht gezählt</span>
                            @endif
                        </td>
                        <td style="text-align:right"><button class="btn btn-ghost btn-sm" wire:click="zaehlen({{ $pos->id }})">übernehmen</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="margin-top:14px;text-align:right">
                <button class="btn btn-primary btn-sm" wire:click="abschliessen({{ $inv->id }})"
                        wire:confirm="Inventur abschließen? Differenzen werden gebucht und der Bestandswert eingefroren.">Inventur abschließen</button>
            </div>
        </div>
    @empty
        <p class="empty">Keine offene Inventur.</p>
    @endforelse

    <div class="card">
        <div class="card-head"><h3>Abgeschlossene Inventuren</h3><span class="badge gray">letzte {{ $abgeschlossene->count() }}</span></div>
        <table class="data-table">
            <thead><tr><th>Stichtag</th><th>Bereich</th><th style="text-align:right">Positionen</th><th style="text-align:right">Bestandswert</th><th>abgeschlossen</th></tr></thead>
            <tbody>
            @forelse ($abgeschlossene as $inv)
                <tr>
                    <td>{{ $inv->stichtag->format('d.m.Y') }}</td>
                    <td>{{ $inv->abteilung?->label() ?? 'ganzes Haus' }}</td>
                    <td style="text-align:right">{{ $inv->positionen->count() }}</td>
                    <td style="text-align:right"><b>{{ number_format((float) $inv->bestandswert_summe, 2, ',', '.') }} €</b></td>
                    <td>{{ $inv->abgeschlossen_am?->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><p class="empty">Noch keine abgeschlossene Inventur.</p></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
