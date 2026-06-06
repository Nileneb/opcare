<div>
    @php
        $bedarf = $vertretungen->filter(fn ($v) => $v->berichtAmpel() === 'red' || $v->vertretungAmpel() === 'red')->count()
            + $ereignisse->filter(fn ($e) => $e->ampel() === 'red')->count();
    @endphp
    <div class="page-head">
        <div><p class="kicker">Bewohner · Rechtliche Vertretung</p><h1>Vertretungen & Stakeholder</h1>
            <p class="lead">Betreuer:innen/Bevollmächtigte mit Aufgabenkreisen (§§ 1814/1815 BGB), Pflicht-mit-Frist
                (§ 1863 Bericht / § 1865 Rechnungslegung) und Ereignis-Beteiligung (§ 1821). Login-Konten geben der
                Vertretung read-only Einsicht in die Daten ihrer Aufgabenkreise.</p></div>
        @if ($bedarf > 0)
            <span class="badge red" style="align-self:center">{{ $bedarf }} mit Handlungsbedarf</span>
        @else
            <span class="badge green" style="align-self:center">alle aktuell</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @error('konto')<div class="flash" style="background:#fde8e8">{{ $message }}</div>@enderror

    <div class="card">
        <div class="card-head"><h3>Vertretungen</h3></div>
        <table class="data">
            <thead><tr><th>Bewohner:in</th><th>Vertretung</th><th>Aufgabenkreise</th><th>Bestellung</th><th>Pflicht: Bericht</th><th>Konto</th><th></th></tr></thead>
            <tbody>
                @forelse ($vertretungen as $x)
                    <tr>
                        <td><b>{{ $x->resident->name }}</b></td>
                        <td>{{ $x->name }}<br><span class="muted" style="font-size:.8em">{{ $x->typ->label() }}@if ($x->beruflich) · beruflich @endif</span></td>
                        <td>
                            @forelse ($x->aufgabenkreiseEnums() as $k)
                                <span class="badge gray" style="font-size:.75em">{{ $k->label() }}</span>
                            @empty
                                <span class="muted">—</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="badge {{ $x->vertretungAmpel() }}">{{ $x->gueltig_bis?->format('d.m.Y') ?? 'unbefristet' }}</span>
                            @if ($x->aktenzeichen)<br><span class="muted" style="font-size:.8em">{{ $x->gericht }} · {{ $x->aktenzeichen }}</span>@endif
                        </td>
                        <td>
                            @if ($x->naechsterBericht())
                                <span class="badge {{ $x->berichtAmpel() }}">fällig {{ $x->naechsterBericht()->format('d.m.Y') }}</span>
                                <button class="btn btn-ghost btn-sm" wire:click="berichtErledigt({{ $x->id }})">erledigt</button>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($x->user)
                                <span class="badge green" style="font-size:.75em">{{ $x->user->email }}</span>
                            @elseif ($x->email)
                                <button class="btn btn-ghost btn-sm" wire:click="kontoAnlegen({{ $x->id }})" wire:confirm="Login-Konto für {{ $x->email }} anlegen?">Konto anlegen</button>
                            @else
                                <span class="muted">keine E-Mail</span>
                            @endif
                        </td>
                        <td><button class="btn btn-ghost btn-sm" wire:click="vertretungLoeschen({{ $x->id }})" wire:confirm="Vertretung entfernen?">entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Noch keine Vertretungen erfasst.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->darfVerwalten())
    <div class="card">
        <div class="card-head"><h3>Vertretung anlegen</h3></div>
        <form wire:submit="vertretungAnlegen">
            <div class="form-row-3">
                <div class="field"><label>Bewohner:in *</label><select wire:model="v_resident_id"><option value="">– wählen –</option>@foreach ($residents as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>@error('v_resident_id')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Typ</label><select wire:model="v_typ">@foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Name *</label><input type="text" wire:model="v_name" />@error('v_name')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field">
                <label>Aufgabenkreise (§ 1815 BGB)</label>
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    @foreach ($kreise as $k)
                        <label class="muted" style="display:flex;align-items:center;gap:4px;font-weight:normal">
                            <input type="checkbox" wire:model="v_kreise" value="{{ $k->value }}" /> {{ $k->label() }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>E-Mail (für Login-Konto)</label><input type="email" wire:model="v_email" />@error('v_email')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Telefon/Kontakt</label><input type="text" wire:model="v_kontakt" /></div>
                <div class="field"><label>Bestellung gültig bis</label><input type="date" wire:model="v_gueltig_bis" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Betreuungsgericht</label><input type="text" wire:model="v_gericht" /></div>
                <div class="field"><label>Aktenzeichen</label><input type="text" wire:model="v_aktenzeichen" /></div>
                <div class="field"><label>Bericht-Intervall (Monate, § 1863)</label><input type="number" wire:model="v_bericht_intervall" placeholder="z. B. 12" />@error('v_bericht_intervall')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <label class="muted" style="display:flex;align-items:center;gap:4px;font-weight:normal;margin-bottom:8px"><input type="checkbox" wire:model="v_beruflich" /> beruflich registriert (BtOG § 23)</label>
            <button class="btn btn-primary btn-sm">+ Vertretung</button>
        </form>
    </div>
    @endif

    <div class="card">
        <div class="card-head"><h3>Ereignisse — Beteiligungs-/Informationsrecht (§ 1821 BGB)</h3></div>
        @forelse ($ereignisse as $e)
            <div class="qm-item">
                <div class="qm-anf">
                    <span class="badge {{ $e->ampel() }}">{{ $e->datum->format('d.m.Y') }}</span>
                    <span class="badge gray" style="font-size:.75em">{{ $e->kategorie->label() }}</span>
                    <b>{{ $e->titel }}</b>
                    <span class="muted">· {{ $e->resident->name }} · {{ str_replace('offen', 'offen — zu informieren', $e->status) }}</span>
                    <span style="margin-left:auto">
                        @if ($e->offen())
                            <button class="btn btn-ghost btn-sm" wire:click="ereignisInformiert({{ $e->id }})">informiert</button>
                        @endif
                        @if ($e->status !== 'erledigt')
                            <button class="btn btn-ghost btn-sm" wire:click="ereignisErledigt({{ $e->id }})">abschließen</button>
                        @endif
                    </span>
                </div>
                @if ($e->beschreibung)<p class="muted" style="margin:2px 0 4px">{{ $e->beschreibung }}</p>@endif
                <p class="muted" style="font-size:.8em;margin:0">Recht: {{ $e->kategorie->rechtsbasis() }} · {{ $e->empfaenger()->count() }} berechtigte Vertretung(en)@if ($e->informiert_am) · informiert {{ $e->informiert_am->format('d.m.Y') }}@endif</p>
            </div>
        @empty
            <p class="empty">Noch keine Ereignisse erfasst.</p>
        @endforelse

        @if ($this->darfVerwalten())
        <form wire:submit="ereignisErfassen" style="margin-top:12px">
            <div class="form-row-3">
                <div class="field"><label>Bewohner:in *</label><select wire:model="e_resident_id"><option value="">– wählen –</option>@foreach ($residents as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>@error('e_resident_id')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Kategorie</label><select wire:model="e_kategorie">@foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Datum *</label><input type="date" wire:model="e_datum" />@error('e_datum')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Titel *</label><input type="text" wire:model="e_titel" placeholder="z. B. MD-Begutachtung Pflegegrad am …" />@error('e_titel')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Beschreibung</label><textarea wire:model="e_beschreibung" rows="2"></textarea></div>
            <button class="btn btn-primary btn-sm">+ Ereignis melden & Vertretungen benachrichtigen</button>
        </form>
        @endif
    </div>
</div>
