<div>
    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Finanzen</p>
            <h1>Pflegehilfsmittel-Verbrauch</h1>
            <p class="lead">Bewohnerbezogene Kosten für zum Verbrauch bestimmte Pflegehilfsmittel (PG 54) im gewählten Monat.</p>
        </div>
    </div>

    <div class="card" style="border-left:4px solid var(--amber, #f59e0b);background:var(--bg-warm, #fffbeb)">
        <p><strong>Rechtshinweis § 40 Abs. 2 SGB XI:</strong>
        § 40 Abs. 2 SGB XI deckelt die Pflegekassen-Pauschale für zum Verbrauch bestimmte Pflegehilfsmittel (PG 54)
        auf 42 €/Monat — nur ambulant/häuslich. Bei vollstationärer Pflege trägt der Träger diese Mittel über den
        Pflegesatz; diese Auswertung dient dann der internen Kostentransparenz.</p>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Monatsauswertung</h3>
            <div style="display:flex;align-items:center;gap:8px">
                <span class="badge gray">Referenz: {{ number_format($pauschale, 2, ',', '.') }} €/Monat (§ 40 SGB XI)</span>
                <input type="month" wire:model.live="monat" style="border:1px solid var(--line-cool);border-radius:6px;padding:4px 8px;font-size:.85rem" />
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Bewohner</th>
                    <th style="text-align:right">Kosten (€)</th>
                    <th style="text-align:right">Auslastung</th>
                    <th style="text-align:center">Ampel</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($eintraege as $eintrag)
                    @php($ampelBadge = match ($eintrag['ampel']) { 'gruen' => 'green', 'amber' => 'amber', 'rot' => 'red', default => 'gray' })
                    <tr>
                        <td><b>{{ $eintrag['resident']->name }}</b></td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums">
                            {{ number_format($eintrag['summe'], 2, ',', '.') }} €
                        </td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums">
                            {{ $eintrag['prozent'] }} %
                            <span style="display:inline-block;width:80px;height:6px;background:var(--line-cool);border-radius:3px;vertical-align:middle;margin-left:6px">
                                <span style="display:block;width:{{ min($eintrag['prozent'], 100) }}%;height:100%;background:{{ $eintrag['ampel'] === 'gruen' ? 'var(--green,#22c55e)' : ($eintrag['ampel'] === 'amber' ? 'var(--amber,#f59e0b)' : 'var(--red,#ef4444)') }};border-radius:3px"></span>
                            </span>
                        </td>
                        <td style="text-align:center">
                            <span class="badge {{ $ampelBadge }}">
                                {{ match ($eintrag['ampel']) { 'gruen' => 'ok', 'amber' => 'Achtung', 'rot' => 'Limit', default => '?' } }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <p class="empty">Kein bewohnerbezogener Pflegehilfsmittel-Verbrauch im gewählten Monat erfasst.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
