<div>
    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Finanzen · Lebensmittelsicherheit</p>
            <h1>Charge / MHD-Rückverfolgung</h1>
            <p class="lead">Rückverfolgbarkeit nach Art. 18 VO (EG) 178/2002 — Lebensmittelunternehmer-Pflicht.</p>
        </div>
    </div>

    <div class="card" style="margin-bottom:12px;border-left:3px solid var(--amber,#f59e0b);padding-left:12px">
        <p class="muted" style="margin:0"><b>Rechtsrahmen:</b>
            <b>Lieferant = „eine Stufe zurück" (Art. 18 Abs. 2 — Pflicht)</b> für Lebensmittelunternehmer (Großküche).
            Die Vorwärts-Verfolgung zum Bewohner/zur Abteilung ist <b>interner Rückruf-Mehrwert</b>
            (kein Art.-18-Pflicht-Element, da Abgabe an Endverbraucher).
            <br>Aufbewahrungshinweis (BVL): Rückverfolgungsdokumente <b>5 Jahre</b>, bei kurz-MHD-Artikeln
            empfohlen MHD + 6 Monate. Kein Auto-Löschen — Archivierung manuell sicherstellen.
        </p>
    </div>

    {{-- MHD-Ablaufliste --}}
    <div class="card">
        <div class="card-head">
            <h3>MHD-Übersicht</h3>
            <span class="badge gray">offene Bestände · Vorlauf 14 Tage</span>
        </div>
        @if ($mhdListe->isEmpty())
            <p class="empty">Keine Artikel mit ablaufendem oder abgelaufenem MHD im Vorlauf.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Artikel</th>
                        <th>Charge</th>
                        <th>MHD</th>
                        <th style="text-align:right">Restmenge</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mhdListe as $zeile)
                        @php($schicht = $zeile['schicht'])
                        <tr>
                            <td><b>{{ $zeile['artikel'] }}</b></td>
                            <td>{{ $schicht->charge_nr ?? '–' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($zeile['mhd'])->format('d.m.Y') }}</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">
                                {{ number_format($schicht->menge_rest, 2, ',', '.') }}
                                {{ $schicht->artikel->einheit }}
                            </td>
                            <td>
                                @if ($zeile['abgelaufen'])
                                    <span class="badge red">Abgelaufen</span>
                                @else
                                    <span class="badge amber">Läuft ab</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Chargensuche --}}
    <div class="card">
        <div class="card-head">
            <h3>Chargensuche</h3>
            <span class="badge gray">Art. 18-Rückverfolgung</span>
        </div>
        <div class="field" style="max-width:400px;margin-bottom:10px">
            <label>Chargen-/Losnummer</label>
            <input type="text" wire:model.live="charge" placeholder="z. B. L-123" />
        </div>

        @if ($charge !== '' && count($chargenTreffer) === 0)
            <p class="empty">Keine Charge „{{ $charge }}" im aktuellen Mandanten gefunden.</p>
        @endif

        @foreach ($chargenTreffer as $treffer)
            @php($schicht = $treffer['schicht'])
            <div style="border:1px solid var(--line-cool);border-radius:8px;padding:12px;margin-bottom:10px">
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px">
                    <div><span class="kicker">Artikel</span><br><b>{{ $treffer['artikel'] }}</b>
                        <span class="muted">/ {{ $treffer['abteilung'] }}</span></div>
                    <div>
                        <span class="kicker">Lieferant <span class="badge green" style="font-size:10px">Art. 18 — Pflicht</span></span><br>
                        @if ($treffer['lieferant'])
                            <b>{{ $treffer['lieferant'] }}</b>
                        @else
                            <span class="muted">nicht erfasst</span>
                        @endif
                    </div>
                    <div><span class="kicker">MHD</span><br>
                        {{ $treffer['mhd'] ? \Illuminate\Support\Carbon::parse($treffer['mhd'])->format('d.m.Y') : '–' }}</div>
                    <div><span class="kicker">Eingang</span><br>
                        {{ number_format($treffer['menge_eingang'], 2, ',', '.') }} {{ $schicht->artikel->einheit }}</div>
                    <div><span class="kicker">Rest</span><br>
                        {{ number_format($treffer['menge_rest'], 2, ',', '.') }} {{ $schicht->artikel->einheit }}</div>
                </div>

                @if (count($treffer['abgaenge']) > 0)
                    <p class="kicker" style="margin:8px 0 4px">
                        Abgänge
                        <span class="badge gray" style="font-size:10px">interner Rückruf · kein Art.-18-Pflicht-Element (Endverbraucher)</span>
                    </p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:110px">Datum</th>
                                <th>Abteilung</th>
                                <th>Bewohner</th>
                                <th style="text-align:right">Menge</th>
                                <th>Notiz</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($treffer['abgaenge'] as $abgang)
                                <tr>
                                    <td>{{ $abgang['datum'] ? \Illuminate\Support\Carbon::parse($abgang['datum'])->format('d.m.Y') : '–' }}</td>
                                    <td>{{ $abgang['abteilung'] }}</td>
                                    <td>{{ $abgang['resident'] ?? '–' }}</td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums">
                                        {{ number_format($abgang['menge'], 2, ',', '.') }}
                                    </td>
                                    <td>{{ $abgang['notiz'] ?? '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="muted" style="margin-top:6px">Noch keine Abgänge zu dieser Charge.</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
