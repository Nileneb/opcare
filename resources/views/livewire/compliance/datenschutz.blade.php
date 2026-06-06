<div>
    <div class="page-head">
        <div><p class="kicker">Verwaltung · Datenschutz</p><h1>Datenschutz-Register</h1>
            <p class="lead">Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) und Auftragsverarbeitungen
                (Art. 28 DSGVO) — editierbarer Katalog mit Prüf-Frist-Ampel. Gesundheitsdaten sind besondere
                Kategorien (Art. 9 DSGVO / § 22 BDSG). Der Art-30-Export erzeugt das vorlagefähige Verzeichnis
                für die Aufsichtsbehörde.</p></div>
        @if ($offeneAvv > 0 || $ueberfaellig > 0)
            <span class="badge red" style="align-self:center">{{ $offeneAvv + $ueberfaellig }} mit Handlungsbedarf</span>
        @else
            <span class="badge green" style="align-self:center">alle aktuell</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Verzeichnis von Verarbeitungstätigkeiten (Art. 30)</h3>
            <button class="btn btn-ghost btn-sm" wire:click="exportArt30" style="margin-left:auto">Art-30-Export</button></div>
        <table class="data">
            <thead><tr><th>Verarbeitung</th><th>Rechtsgrundlage</th><th>Betroffene / Daten</th><th>Löschfrist</th><th>Prüfung</th><th></th></tr></thead>
            <tbody>
                @forelse ($vts as $v)
                    <tr>
                        <td><b>{{ $v->name }}</b><br><span class="muted" style="font-size:.8em">{{ $v->zweck }}</span></td>
                        <td>{{ $v->rechtsgrundlage->label() }}<br><span class="muted" style="font-size:.8em">{{ $v->rechtsgrundlage->artikel() }}</span></td>
                        <td>{{ $v->kategorien_betroffene }}<br><span class="muted" style="font-size:.8em">{{ $v->kategorien_daten }}</span></td>
                        <td>{{ $v->loeschfrist }}</td>
                        <td><span class="badge {{ $v->ampel() }}">{{ $v->geprueft_am?->format('d.m.Y') ?? 'ungeprüft' }}</span>
                            <button class="btn btn-ghost btn-sm" wire:click="verarbeitungGeprueft({{ $v->id }})">geprüft</button></td>
                        <td><button class="btn btn-ghost btn-sm" wire:click="verarbeitungLoeschen({{ $v->id }})" wire:confirm="Eintrag entfernen?">entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Noch keine Verarbeitungstätigkeiten erfasst.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Verarbeitungstätigkeit erfassen</h3></div>
        <form wire:submit="verarbeitungAnlegen">
            <div class="form-row-3">
                <div class="field"><label>Bezeichnung *</label><input type="text" wire:model="v_name" />@error('v_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Rechtsgrundlage</label><select wire:model="v_rechtsgrundlage">@foreach ($rechtsgrundlagen as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Prüf-Intervall (Monate)</label><input type="number" wire:model="v_intervall" />@error('v_intervall')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Zweck *</label><textarea wire:model="v_zweck" rows="2"></textarea>@error('v_zweck')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="form-row-3">
                <div class="field"><label>Kategorien Betroffener *</label><input type="text" wire:model="v_betroffene" placeholder="z. B. Bewohner:innen" />@error('v_betroffene')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Kategorien Daten *</label><input type="text" wire:model="v_daten" placeholder="z. B. Gesundheitsdaten" />@error('v_daten')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Empfänger</label><input type="text" wire:model="v_empfaenger" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Drittlandtransfer</label><input type="text" wire:model="v_drittland" placeholder="nein / Land + Garantie" /></div>
                <div class="field"><label>Löschfrist *</label><input type="text" wire:model="v_loeschfrist" placeholder="z. B. 10 Jahre (§ 630f BGB)" />@error('v_loeschfrist')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>TOM (Verweis)</label><input type="text" wire:model="v_tom" /></div>
            </div>
            <button class="btn btn-primary btn-sm">+ Verarbeitungstätigkeit</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Auftragsverarbeitungen (Art. 28)</h3></div>
        <table class="data">
            <thead><tr><th>Dienstleister</th><th>Zweck / Daten</th><th>Drittland</th><th>AVV</th><th></th></tr></thead>
            <tbody>
                @forelse ($avvs as $a)
                    <tr>
                        <td><b>{{ $a->dienstleister }}</b>@if ($a->unterauftragnehmer)<br><span class="muted" style="font-size:.8em">mit Unterauftragnehmern</span>@endif</td>
                        <td>{{ $a->zweck }}<br><span class="muted" style="font-size:.8em">{{ $a->kategorien_daten }}</span></td>
                        <td>{{ $a->drittland ?? 'nein' }}</td>
                        <td>
                            @if ($a->vertrag_geschlossen_am)
                                <span class="badge {{ $a->ampel() }}">AVV {{ $a->vertrag_geschlossen_am->format('d.m.Y') }}</span>
                                <button class="btn btn-ghost btn-sm" wire:click="avvGeprueft({{ $a->id }})">geprüft</button>
                            @else
                                <span class="badge red">kein AVV</span>
                            @endif
                        </td>
                        <td><button class="btn btn-ghost btn-sm" wire:click="avvLoeschen({{ $a->id }})" wire:confirm="Eintrag entfernen?">entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Auftragsverarbeitungen erfasst.</td></tr>
                @endforelse
            </tbody>
        </table>
        <form wire:submit="avvAnlegen" style="margin-top:12px">
            <div class="form-row-3">
                <div class="field"><label>Dienstleister *</label><input type="text" wire:model="a_dienstleister" />@error('a_dienstleister')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Verknüpfte Verarbeitung</label><select wire:model="a_vt"><option value="">– keine –</option>@foreach ($vts as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></div>
                <div class="field"><label>AVV geschlossen am</label><input type="date" wire:model="a_vertrag_am" /></div>
            </div>
            <div class="field"><label>Zweck *</label><textarea wire:model="a_zweck" rows="2"></textarea>@error('a_zweck')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="form-row-3">
                <div class="field"><label>Kategorien Daten *</label><input type="text" wire:model="a_daten" />@error('a_daten')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Drittland</label><input type="text" wire:model="a_drittland" /></div>
                <div class="field"><label>Prüf-Intervall (Monate)</label><input type="number" wire:model="a_intervall" />@error('a_intervall')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <label class="muted" style="display:flex;align-items:center;gap:4px;font-weight:normal;margin-bottom:8px"><input type="checkbox" wire:model="a_unterauftrag" /> setzt Unterauftragnehmer ein (Art. 28 Abs. 2/4)</label>
            <button class="btn btn-primary btn-sm">+ Auftragsverarbeitung</button>
        </form>
    </div>
</div>
