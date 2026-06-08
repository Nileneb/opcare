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

    {{-- Selbst-Ampel (Mode B/C) — nur sichtbar wenn per Beschluss freigeschaltet --}}
    <div class="card">
        <div class="card-head">
            <h3>Meine Belastung (Selbst-Ampel)</h3>
            @if ($belastungFreigeschaltet)
                <span class="badge green">freigeschaltet</span>
            @else
                <span class="badge gray">nicht aktiv</span>
            @endif
        </div>
        @if ($belastungFreigeschaltet)
            <p class="muted" style="margin-bottom:12px">Wie stark fühlst du dich gerade belastet? 0 = sehr stark belastet, 10 = gut entlastet.
                Nur du siehst deinen Wert — Vorgesetzte sehen ausschließlich eine explizit von dir gemeldete Überlastung.</p>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <input type="range" min="0" max="10" step="1"
                    wire:model.live="meineBelastung"
                    style="flex:1;min-width:160px;max-width:320px"
                    @if($meineBelastung === null) value="5" @endif />
                @if($meineBelastung !== null)
                    <div style="width:36px;height:36px;border-radius:50%;border:2px solid #e5e7eb;flex-shrink:0;background:{{ \App\Domains\Arbeitsschutz\Support\BelastungsAmpel::farbe($meineBelastung) }}"
                         title="Lage {{ $meineBelastung }}/10"></div>
                @endif
                <button class="btn btn-primary btn-sm" wire:click="belastungSetzen({{ $meineBelastung ?? 5 }})">Speichern</button>
            </div>
            @if($meineBelastung !== null)
                <div style="margin-top:12px;display:flex;align-items:center;gap:8px">
                    <div style="width:24px;height:24px;border-radius:4px;background:{{ \App\Domains\Arbeitsschutz\Support\BelastungsAmpel::farbe($meineBelastung) }}"></div>
                    <span class="muted" style="font-size:.85em">Lage {{ $meineBelastung }}/10
                        @if($meineBelastung <= 2) — stark belastet
                        @elseif($meineBelastung <= 5) — mäßig belastet
                        @elseif($meineBelastung <= 7) — moderat
                        @else — gut
                        @endif
                    </span>
                </div>
                <div style="margin-top:12px">
                    <label style="font-weight:500;display:block;margin-bottom:4px">Notiz (optional)</label>
                    <textarea wire:model="belastungNotiz" rows="2" style="width:100%;max-width:480px"
                        placeholder="Was belastet dich? (freiwillig, wird an Leitung übermittelt wenn du meldest)"></textarea>
                </div>
                <p style="margin-top:10px">
                    <button class="btn btn-primary btn-sm" wire:click="ueberlastungMelden"
                        wire:confirm="Überlastung an Leitung melden?">
                        Überlastung an Leitung melden
                    </button>
                </p>
            @endif
            <p class="muted" style="margin-top:8px;font-size:.82em">
                Die Selbst-Ampel wurde per Mitarbeitenden-Beschluss freigeschaltet (§ 87 Abs. 1 Nr. 6 BetrVG).
                Die Meldung an die Leitung erfolgt ausschließlich durch deinen eigenen Knopfdruck — kein Auto-Monitoring.
            </p>
            <p class="muted" style="font-size:.8em">Legende: <span style="color:hsl(0,75%,45%)">rot</span> = stark belastet · <span style="color:hsl(50,75%,45%)">gelb</span> = mäßig · <span style="color:hsl(120,75%,45%)">grün</span> = entlastet</p>
        @else
            <p class="muted">Die individuelle Selbst-Ampel und Selbst-Überlastungsmeldung sind durch einen
                <strong>Mitarbeitenden-Beschluss freischaltbar</strong> (§ 87 Abs. 1 Nr. 6 BetrVG).
                Eine Administrationsperson kann die Freischaltung nach einem entsprechenden Beschluss unter
                <em>Planung → Arbeitsrecht-Regeln</em> aktivieren.</p>
        @endif
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
