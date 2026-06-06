<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Wohlbefinden</p><h1>Team-Energiebarometer</h1>
            <p class="lead">Freiwillige, anonyme Stimmungsabfrage — wie viel Energie hast du heute? Ein Frühwarnsignal
                gegen Überlastung, kein Leistungs-Monitoring.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Mein Energie-Level heute</h3>
            @if ($meine)<span class="badge gray">gesetzt</span>@else<span class="badge gray">freiwillig</span>@endif
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            @foreach ($stufen as $s)
                <button type="button" wire:click="setzen({{ $s->value }})"
                    @class(['btn', 'btn-primary' => $meine === $s->value, 'btn-ghost' => $meine !== $s->value])>
                    {{ $s->emoji() }} {{ $s->label() }}
                </button>
            @endforeach
        </div>
        @if ($meine)
            <p style="margin-top:10px"><button class="btn btn-ghost btn-sm" wire:click="zuruecknehmen">Rückmeldung zurücknehmen</button></p>
        @endif
        <p class="muted" style="margin-top:8px">Die Teilnahme ist freiwillig (§ 26 BDSG). Es wird nur dein aktueller Wert gespeichert —
            <b>kein Verlauf</b>. Andere sehen nie deinen persönlichen Wert, nur den anonymen Hausschnitt.</p>
    </div>

    <div class="card">
        <div class="card-head"><h3>Hausschnitt (anonym)</h3>
            <span class="badge {{ $hausAmpel }}">{{ $gesamt }} {{ $gesamt === 1 ? 'Rückmeldung' : 'Rückmeldungen' }}</span>
        </div>
        @if ($auswertbar)
            <table class="data-table">
                <thead><tr><th>Stufe</th><th style="text-align:right;width:120px">Anzahl</th></tr></thead>
                <tbody>
                    @foreach ($stufen as $s)
                        <tr>
                            <td><span class="badge {{ $s->ampel() }}">{{ $s->emoji() }} {{ $s->label() }}</span></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $verteilung[$s->value] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Noch zu wenige Rückmeldungen für eine anonyme Auswertung (mind. {{ $minAuswertbar }}).
                So lässt sich aus dem Hausschnitt keine einzelne Person rückschließen.</p>
        @endif
        <p class="muted" style="margin-top:8px">Die Einführung eines solchen Barometers ist nach § 87 Abs. 1 Nr. 6 BetrVG
            mitbestimmungspflichtig (Betriebsrat/Mitarbeitervertretung).</p>
    </div>
</div>
