<div>
    <div class="page-head">
        <div>
            <p class="kicker">Wohnbereich Aprath</p>
            <h1>Übersicht</h1>
            <p class="lead">Pflegedokumentation auf einen Blick — Stammdaten, SIS®-Planung und Spracherfassung.</p>
        </div>
        <div class="btn-row">
            <a href="{{ route('bewohner') }}" class="btn btn-ghost" wire:navigate>Bewohner verwalten</a>
            <a href="{{ route('pflegeplanung') }}" class="btn btn-primary">SIS-Board öffnen</a>
        </div>
    </div>

    <div class="grid-4" style="margin-bottom:var(--space-5)">
        <div class="stat"><div class="n">{{ $stats['residents'] }}</div><div class="l">Aktive Bewohner:innen</div></div>
        <div class="stat"><div class="n">{{ $stats['sis'] }}</div><div class="l">Aktive SIS-Erhebungen</div></div>
        <div class="stat"><div class="n">{{ $stats['measures'] }}</div><div class="l">Geplante Maßnahmen</div></div>
        <div class="stat"><div class="n">{{ $stats['review'] }}</div><div class="l">Sprachnotizen zur Freigabe</div></div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3>Bewohner:innen</h3>
                <a href="{{ route('bewohner') }}" class="btn btn-ghost btn-sm" wire:navigate>Alle anzeigen</a>
            </div>
            @if ($residents->isEmpty())
                <p class="empty">Noch keine Bewohner angelegt.</p>
            @else
                <table class="data">
                    <thead><tr><th>Name</th><th>Zimmer</th><th>PG</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($residents as $r)
                            <tr class="clickable" onclick="window.location='{{ route('bewohner.show', $r) }}'">
                                <td><b>{{ $r->name }}</b></td>
                                <td>{{ $r->room?->nummer ?? '—' }}</td>
                                <td>{{ $r->pflegegrad ?? '—' }}</td>
                                <td style="text-align:right"><a href="{{ route('bewohner.show', $r) }}" class="btn btn-ghost btn-sm" wire:navigate>Öffnen</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Schnellzugriff</h3></div>
            <div class="chip-list">
                <a class="chip" href="{{ route('bewohner') }}" wire:navigate style="text-decoration:none;color:inherit"><b>Bewohner & Stammdaten</b> — anlegen, Diagnosen, Kassen, Betreuer, Ärzte</a>
                <a class="chip" href="{{ route('pflegeplanung') }}" style="text-decoration:none;color:inherit"><b>SIS®-Board</b> — 6 Lebensbereiche, Ampel, Detailansicht</a>
                <a class="chip" href="{{ route('spracherfassung') }}" wire:navigate style="text-decoration:none;color:inherit"><b>Spracherfassung</b> — Sprachnotiz → KI-Vorschlag → Freigabe</a>
                <a class="chip" href="{{ route('einrichtung') }}" wire:navigate style="text-decoration:none;color:inherit"><b>Einrichtung</b> — Gebäude, Zimmer, ICD, Kassen, Ärzte</a>
            </div>
        </div>
    </div>
</div>
