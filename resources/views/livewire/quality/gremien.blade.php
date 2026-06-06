<div>
    <div class="page-head">
        <div><p class="kicker">Qualität · Mitwirkung</p><h1>Gremien & Heimbeirat</h1>
            <p class="lead">Heimbeirat/Bewohnervertretung (HeimmwV, § 10 WBVG), Angehörigenbeirat, Qualitätszirkel (§ 113 SGB XI) und Arbeitsschutzausschuss (§ 11 ASiG) mit Wahlperioden- und Sitzungs-Ampel.</p></div>
        @if ($handlungsbedarf > 0)
            <span class="badge amber" style="align-self:center">{{ $handlungsbedarf }} mit Handlungsbedarf</span>
        @else
            <span class="badge green" style="align-self:center">alle aktuell</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Gremien</h3></div>
        <table class="data">
            <thead><tr><th>Gremium</th><th>Typ / Basis</th><th>Mitglieder</th><th>Letzte Sitzung</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($gremien as $x)
                    <tr wire:click="select({{ $x->id }})" style="cursor:pointer" @class(['is-active' => $gremium && $gremium->id === $x->id])>
                        <td><b>{{ $x->name }}</b></td>
                        <td>{{ $x->typ->label() }}<br><span class="muted" style="font-size:.8em">{{ $x->typ->rechtsbasis() }}</span></td>
                        <td>{{ $x->mitglieder_count }}</td>
                        <td>
                            @php $s = $x->sitzungen()->max('datum'); @endphp
                            {{ $s ? \Illuminate\Support\Carbon::parse($s)->format('d.m.Y') : '—' }}
                        </td>
                        <td><span class="badge {{ $x->ampel() }}">{{ str_replace('_', ' ', $x->status()) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Gremien angelegt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Gremium anlegen</h3></div>
        <form wire:submit="anlegen">
            <div class="form-row-3">
                <div class="field"><label>Typ</label><select wire:model.live="g_typ">@foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Name *</label><input type="text" wire:model="g_name" placeholder="z. B. Heimbeirat 2026" />@error('g_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Gewählt / konstituiert am</label><input type="date" wire:model="g_gewaehlt_am" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Wahlperiode (Monate)</label><input type="number" wire:model="g_periode" placeholder="z. B. 24" />@error('g_periode')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Soll-Sitzungstakt (Monate)</label><input type="number" wire:model="g_sitzung_intervall" placeholder="z. B. 3" />@error('g_sitzung_intervall')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Beschreibung</label><input type="text" wire:model="g_beschreibung" /></div>
            </div>
            <button class="btn btn-primary btn-sm">+ Gremium</button>
        </form>
    </div>

    @if ($gremium)
        <div class="card">
            <div class="card-head"><h3>{{ $gremium->name }}</h3><span class="badge {{ $gremium->ampel() }}">{{ str_replace('_', ' ', $gremium->status()) }}</span></div>
            <div class="grid-3">
                <div><span class="muted">Typ</span><br>{{ $gremium->typ->label() }}</div>
                <div><span class="muted">Rechtsbasis</span><br>{{ $gremium->typ->rechtsbasis() }}</div>
                <div><span class="muted">Gewählt am</span><br>{{ $gremium->gewaehlt_am?->format('d.m.Y') ?? '—' }}</div>
                <div><span class="muted">Wahlperiode endet</span><br>{{ $gremium->periodeEndet()?->format('d.m.Y') ?? '—' }}</div>
                <div><span class="muted">Nächste Sitzung fällig</span><br>{{ $gremium->naechsteSitzungFaellig()?->format('d.m.Y') ?? '—' }}</div>
                <div><span class="muted">Sitzungen</span><br>{{ $gremium->sitzungen_count }}</div>
            </div>
            @if ($gremium->beschreibung)<p class="muted" style="margin-top:6px">{{ $gremium->beschreibung }}</p>@endif
            @if ($gremium->aktiv())
                <div style="display:flex;gap:8px;margin-top:8px">
                    <button class="btn btn-ghost btn-sm" wire:click="neuGewaehlt" wire:confirm="Neuwahl/Konstituierung auf heute setzen?">Neuwahl dokumentieren</button>
                    <button class="btn btn-ghost btn-sm" wire:click="aufloesen" wire:confirm="Gremium auflösen?">Auflösen</button>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Mitglieder</h3></div>
            @forelse ($mitglieder as $m)
                <div class="qm-anf">
                    <span class="badge {{ $m->funktion->value === 'vorsitz' ? 'green' : 'gray' }}">{{ $m->funktion->label() }}</span>
                    <b>{{ $m->name }}</b>
                    <span class="muted">· {{ $m->art->label() }}@if ($m->bis) · bis {{ $m->bis->format('d.m.Y') }}@endif</span>
                    <button class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="mitgliedEntfernen({{ $m->id }})" wire:confirm="Mitglied entfernen?">entfernen</button>
                </div>
            @empty
                <p class="empty">Noch keine Mitglieder.</p>
            @endforelse

            <form wire:submit="mitgliedHinzufuegen" style="margin-top:12px">
                <div class="form-row-3">
                    <div class="field"><label>Name *</label><input type="text" wire:model="m_name" />@error('m_name')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Art</label><select wire:model="m_art">@foreach ($arten as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach</select></div>
                    <div class="field"><label>Funktion</label><select wire:model="m_funktion">@foreach ($funktionen as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach</select></div>
                </div>
                <div class="form-row-2">
                    <div class="field"><label>Verknüpfter Mitarbeiter (optional)</label><select wire:model="m_user"><option value="">–</option>@foreach ($mitarbeiter as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Verknüpfte:r Bewohner:in (optional)</label><select wire:model="m_resident"><option value="">–</option>@foreach ($bewohner as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Mitglied</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Sitzungen & Protokolle</h3></div>
            @forelse ($sitzungen as $s)
                <div class="qm-item">
                    <div class="qm-anf">
                        <span class="badge gray">{{ $s->datum->format('d.m.Y') }}</span>
                        <b>{{ $s->thema }}</b>
                        @if ($s->teilnehmerzahl !== null)<span class="muted">· {{ $s->teilnehmerzahl }} TN</span>@endif
                        <span class="muted" style="margin-left:auto">{{ $s->protokollant?->name }}</span>
                    </div>
                    @if ($s->protokoll)<p class="muted" style="margin:2px 0 4px">{{ $s->protokoll }}</p>@endif
                    @if ($s->beschluesse)<p style="margin:2px 0"><b>Beschlüsse:</b> {{ $s->beschluesse }}</p>@endif
                </div>
            @empty
                <p class="empty">Noch keine Sitzungen protokolliert.</p>
            @endforelse

            <form wire:submit="sitzungProtokollieren" style="margin-top:12px">
                <div class="form-row-3">
                    <div class="field"><label>Datum *</label><input type="date" wire:model="s_datum" /></div>
                    <div class="field"><label>Thema *</label><input type="text" wire:model="s_thema" />@error('s_thema')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Teilnehmerzahl</label><input type="number" wire:model="s_teilnehmer" /></div>
                </div>
                <div class="field"><label>Protokoll</label><textarea wire:model="s_protokoll" rows="2"></textarea></div>
                <div class="field"><label>Beschlüsse</label><textarea wire:model="s_beschluesse" rows="2"></textarea></div>
                <button class="btn btn-primary btn-sm">+ Sitzung protokollieren</button>
            </form>
        </div>
    @endif
</div>
