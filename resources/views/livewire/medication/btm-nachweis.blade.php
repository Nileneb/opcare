<div>
    <div class="page-head">
        <div><p class="kicker">Medikation · Betäubungsmittel</p><h1>BtM-Nachweis (§ 13 BtMVV)</h1>
            <p class="lead">Bewohnerbezogene BtM-Konten, fortlaufendes Bestandsbuch und monatlicher Abschluss.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="grid-2" style="align-items:start;gap:18px">
        <div class="card">
            <div class="card-head"><h3>BtM-Konten</h3><span class="badge gray">{{ $konten->count() }}</span></div>
            @forelse ($konten as $k)
                <div class="qm-anf" style="padding:6px 0;border-bottom:1px solid var(--line-cool)">
                    <button class="btn {{ $selected === $k->id ? 'btn-primary' : 'btn-ghost' }} btn-sm" wire:click="$set('selected', {{ $k->id }})">
                        {{ $k->resident?->name }} · {{ $k->substanz }}@if ($k->staerke) {{ $k->staerke }}@endif
                    </button>
                    @unless ($k->offen())<span class="badge gray">geschlossen</span>@endunless
                </div>
            @empty
                <p class="empty">Noch kein BtM-Konto.</p>
            @endforelse

            <form wire:submit="kontoAnlegen" style="margin-top:12px;border-top:1px solid var(--line-cool);padding-top:12px">
                <p class="kicker">Neues Konto (Bewohner + Substanz)</p>
                <div class="field"><label>Bewohner:in</label>
                    <select wire:model="k_resident"><option value="">– wählen –</option>@foreach ($bewohner as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    @error('k_resident')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="form-row-2">
                    <div class="field"><label>Substanz</label><input type="text" wire:model="k_substanz" placeholder="z. B. Morphin" />@error('k_substanz')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Stärke</label><input type="text" wire:model="k_staerke" placeholder="z. B. 10 mg/ml" /></div>
                </div>
                <div class="form-row-2">
                    <div class="field"><label>Einheit</label><input type="text" wire:model="k_einheit" /></div>
                    <div class="field"><label>Verantwortl. Arzt</label><input type="text" wire:model="k_arzt" />@error('k_arzt')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Konto</button>
            </form>
        </div>

        <div>
            @if ($konto)
                <div class="card">
                    <div class="card-head"><h3>{{ $konto->resident?->name }} · {{ $konto->substanz }}</h3>
                        <span class="badge {{ $bestand > 0 ? 'green' : 'gray' }}">Bestand: {{ number_format($bestand, 3, ',', '.') }} {{ $konto->einheit }}</span>
                    </div>

                    <form wire:submit="buchen" style="border:1px solid var(--line-cool);border-radius:8px;padding:12px">
                        <p class="kicker">Buchung</p>
                        <div class="form-row-3">
                            <div class="field"><label>Vorgang</label><select wire:model.live="b_vorgang">@foreach ($vorgaenge as $v)<option value="{{ $v->value }}">{{ $v->label() }}</option>@endforeach</select></div>
                            <div class="field"><label>Menge ({{ $konto->einheit }})</label><input type="number" step="0.001" wire:model="b_menge" />@error('b_menge')<span class="err">{{ $message }}</span>@enderror</div>
                            <div class="field"><label>Datum</label><input type="date" wire:model="b_datum" />@error('b_datum')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        @if ($b_vorgang === 'lieferung')
                            <div class="form-row-2">
                                <div class="field"><label>Lieferant (Apotheke)</label><input type="text" wire:model="b_lieferant" /></div>
                                <div class="field"><label>Verschreibender Arzt</label><input type="text" wire:model="b_arzt" /></div>
                            </div>
                        @elseif ($b_vorgang === 'vernichtung')
                            <div class="form-row-3">
                                <div class="field"><label>Zeuge 1</label><input type="text" wire:model="b_zeuge_1" />@error('b_zeuge_1')<span class="err">{{ $message }}</span>@enderror</div>
                                <div class="field"><label>Zeuge 2</label><input type="text" wire:model="b_zeuge_2" />@error('b_zeuge_2')<span class="err">{{ $message }}</span>@enderror</div>
                                <div class="field"><label>Methode</label><input type="text" wire:model="b_vernichtungsmethode" /></div>
                            </div>
                        @elseif ($b_vorgang === 'transfer' || $b_vorgang === 'ruecknahme')
                            <div class="field"><label>Empfänger (Einrichtung/Apotheke)</label><input type="text" wire:model="b_empfaenger" /></div>
                        @elseif ($b_vorgang === 'korrektur')
                            <div class="field"><label>Grund (Pflicht) — Menge vorzeichenbehaftet</label><input type="text" wire:model="b_grund" />@error('b_grund')<span class="err">{{ $message }}</span>@enderror</div>
                        @endif
                        <button class="btn btn-primary btn-sm">Buchen</button>
                    </form>

                    <table class="data-table" style="margin-top:14px">
                        <thead><tr><th>Nr.</th><th>Datum</th><th>Vorgang</th><th style="text-align:right">Menge</th><th style="text-align:right">Bestand</th><th>Erfasst</th></tr></thead>
                        <tbody>
                            @forelse ($buchungen as $b)
                                <tr>
                                    <td>{{ $b->lfd_nr }}</td>
                                    <td>{{ $b->datum->format('d.m.Y') }}</td>
                                    <td>{{ $b->vorgang->label() }}@if ($b->zeuge_1)<br><span class="muted" style="font-size:.78em">Zeugen: {{ $b->zeuge_1 }}, {{ $b->zeuge_2 }}</span>@endif@if ($b->grund)<br><span class="muted" style="font-size:.78em">{{ $b->grund }}</span>@endif</td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format((float) $b->menge, 3, ',', '.') }}</td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums"><b>{{ number_format((float) $b->bestand_nach, 3, ',', '.') }}</b></td>
                                    <td class="muted" style="font-size:.8em">{{ $b->durchfuehrer?->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><p class="empty">Noch keine Buchung.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Monatsabschluss (§ 13 Abs. 2)</h3></div>
                    @foreach ($abschluesse as $a)
                        <div class="qm-anf" style="padding:5px 0">
                            <b>{{ $a->monat->isoFormat('MMMM YYYY') }}</b>
                            <span>Soll {{ number_format((float) $a->soll_bestand, 3, ',', '.') }} · Ist {{ number_format((float) $a->ist_bestand, 3, ',', '.') }}</span>
                            @if (abs($a->differenz()) > 0.0001)<span class="badge red" title="{{ $a->differenz_notiz }}">Differenz {{ number_format($a->differenz(), 3, ',', '.') }}</span>@else<span class="badge green">o. B.</span>@endif
                            <span class="muted">geprüft: {{ $a->geprueft_von }}, {{ $a->pruef_datum->format('d.m.Y') }}</span>
                            @if ($a->gesperrt_am)<span class="badge gray">gesperrt</span>@endif
                        </div>
                    @endforeach
                    <form wire:submit="monatsabschluss" style="margin-top:10px;border-top:1px solid var(--line-cool);padding-top:10px">
                        <div class="form-row-3">
                            <div class="field"><label>Monat</label><input type="date" wire:model="ab_monat" /></div>
                            <div class="field"><label>Ist-Bestand (Zählung)</label><input type="number" step="0.001" wire:model="ab_ist" />@error('ab_ist')<span class="err">{{ $message }}</span>@enderror</div>
                            <div class="field"><label>Geprüft von (Arzt)</label><input type="text" wire:model="ab_geprueft_von" />@error('ab_geprueft_von')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        <div class="field"><label>Differenz-Begründung (bei Abweichung Pflicht)</label><input type="text" wire:model="ab_notiz" />@error('ab_notiz')<span class="err">{{ $message }}</span>@enderror</div>
                        <button class="btn btn-primary btn-sm">Abschluss speichern + sperren</button>
                    </form>
                    <p class="muted" style="margin-top:10px;font-size:.82em">Aufbewahrung 3 Jahre (§ 13 Abs. 3 BtMVV). Buchungen sind unveränderbar; Korrekturen erfolgen als neue Korrekturbuchung.</p>
                </div>
            @else
                <div class="card"><p class="empty">Konto links wählen oder anlegen.</p></div>
            @endif
        </div>
    </div>
</div>
