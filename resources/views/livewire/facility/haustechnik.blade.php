<div>
    <div class="page-head">
        <div><p class="kicker">Haustechnik · Instandhaltung</p><h1>Haustechnik</h1>
            <p class="lead">Mängel melden und Wartungsfristen im Blick (DIN 31051).</p></div>
        @if ($ueberfaellig > 0)
            <span class="badge red" title="überfällige Prüfungen">{{ $ueberfaellig }} Prüfung(en) überfällig</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Mangel melden</h3></div>
        <form wire:submit="melden">
            <div class="form-row">
                <div class="field"><label>Was ist defekt? *</label><input type="text" wire:model="m_titel" placeholder="z. B. Heizung Zimmer 7 ohne Funktion" />@error('m_titel')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Ort</label><input type="text" wire:model="m_standort" placeholder="z. B. Zimmer 7 / Flur EG" /></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Priorität</label>
                    <select wire:model="m_prioritaet">@foreach ($prioritaeten as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select>
                </div>
                <div class="field"><label>Betriebsmittel (optional)</label>
                    <select wire:model="m_asset"><option value="">– keines –</option>@foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->bezeichnung }}</option>@endforeach</select>
                </div>
            </div>
            <div class="field"><label>Beschreibung</label><textarea wire:model="m_beschreibung" rows="2"></textarea></div>
            <button class="btn btn-primary btn-sm">Melden</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Offene Meldungen</h3><span class="badge {{ $offene->isEmpty() ? 'green' : 'amber' }}">{{ $offene->count() }}</span></div>
        @forelse ($offene as $m)
            <div class="qm-item">
                <div class="qm-anf">
                    <span class="badge {{ $m->prioritaet->badge() }}">{{ $m->prioritaet->label() }}</span>
                    <span class="badge {{ $m->status->badge() }}">{{ $m->status->label() }}</span>
                    <b>{{ $m->titel }}</b>
                    @if ($m->standort)<span class="muted">· {{ $m->standort }}</span>@endif
                    <span class="muted" style="margin-left:auto">{{ $m->melder?->name }} · {{ $m->created_at?->format('d.m.Y') }}</span>
                </div>
                @if ($m->beschreibung)<p class="muted" style="margin:2px 0 8px">{{ $m->beschreibung }}</p>@endif
                @if ($darfVerwalten)
                    <div style="display:flex;gap:8px">
                        @if ($m->status->value === 'offen')<button class="btn btn-ghost btn-sm" wire:click="uebernehmen({{ $m->id }})">Übernehmen</button>@endif
                        <button class="btn btn-primary btn-sm" wire:click="erledigenStart({{ $m->id }})">Erledigt melden</button>
                    </div>
                    @if ($erledigeId === $m->id)
                        <div class="plan-begruenden">
                            <textarea wire:model="erledigt_notiz" rows="2" placeholder="Was wurde gemacht? (optional)"></textarea>
                            <div style="display:flex;gap:8px;margin-top:6px">
                                <button class="btn btn-primary btn-sm" wire:click="erledigen">Speichern</button>
                                <button class="btn btn-ghost btn-sm" wire:click="$set('erledigeId', null)">Abbrechen</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <p class="empty">Keine offenen Meldungen — alles in Ordnung.</p>
        @endforelse
    </div>

    @if ($darfVerwalten)
        <div class="card">
            <div class="card-head"><h3>Wartungsplan (Prüffristen)</h3><span class="badge gray">DIN 31051</span></div>
            <table class="data">
                <thead><tr><th>Betriebsmittel</th><th>Kategorie</th><th>Norm</th><th>Nächste Prüfung</th><th></th></tr></thead>
                <tbody>
                    @forelse ($assets as $a)
                        <tr>
                            <td><b>{{ $a->bezeichnung }}</b>@if ($a->standort)<br><span class="muted">{{ $a->standort }}</span>@endif</td>
                            <td>{{ $a->kategorie->label() }}</td>
                            <td>{{ $a->norm ?? '—' }}</td>
                            <td>
                                @php $np = $a->naechstePruefung(); @endphp
                                @if ($np)<span class="badge {{ $a->ueberfaellig() ? 'red' : ($a->faelligBald() ? 'amber' : 'green') }}">{{ $np->format('d.m.Y') }}</span>@else <span class="muted">— kein Intervall</span> @endif
                            </td>
                            <td><button class="btn btn-ghost btn-sm" wire:click="geprueft({{ $a->id }})">geprüft heute</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Noch keine Betriebsmittel erfasst.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <form wire:submit="assetAnlegen" style="margin-top:14px">
                <div class="form-row-3">
                    <div class="field"><label>Bezeichnung *</label><input type="text" wire:model="a_bezeichnung" placeholder="z. B. Aufzug Haus 1" />@error('a_bezeichnung')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Kategorie</label><select wire:model="a_kategorie">@foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select></div>
                    <div class="field"><label>Norm</label><input type="text" wire:model="a_norm" placeholder="z. B. BetrSichV" /></div>
                </div>
                <div class="form-row-3">
                    <div class="field"><label>Standort</label><input type="text" wire:model="a_standort" /></div>
                    <div class="field"><label>Prüfintervall (Monate)</label><input type="number" min="1" max="120" wire:model="a_intervall" />@error('a_intervall')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Letzte Prüfung</label><input type="date" wire:model="a_letzte" /></div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Betriebsmittel</button>
            </form>
        </div>

        @if ($erledigtKuerzlich->isNotEmpty())
            <div class="card">
                <div class="card-head"><h3>Zuletzt erledigt</h3></div>
                @foreach ($erledigtKuerzlich as $m)
                    <div class="qm-anf"><span class="badge green">erledigt</span> <b>{{ $m->titel }}</b>
                        <span class="muted">· {{ $m->erledigt_am?->format('d.m.Y') }}@if ($m->erledigt_notiz) — {{ $m->erledigt_notiz }}@endif</span></div>
                @endforeach
            </div>
        @endif
    @endif
</div>
