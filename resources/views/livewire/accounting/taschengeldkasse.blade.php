<div>
    <div class="page-head">
        <div><p class="kicker">Finanzen · Treuhand</p><h1>Taschengeldkasse (§ 27b SGB XII)</h1>
            <p class="lead">Bewohnerbezogene Treuhandkonten, getrennt vom Einrichtungsvermögen — Buchungsjournal, Budget-Setzungen und monatliche Rechnungslegung.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="grid-2" style="align-items:start;gap:18px">
        <div class="card">
            <div class="card-head"><h3>Treuhandkonten</h3><span class="badge gray">{{ $konten->count() }}</span></div>
            @forelse ($konten as $k)
                <div class="qm-anf" style="padding:6px 0;border-bottom:1px solid var(--line-cool)">
                    <button class="btn {{ $selected === $k->id ? 'btn-primary' : 'btn-ghost' }} btn-sm" wire:click="$set('selected', {{ $k->id }})">
                        {{ $k->resident?->name }}
                    </button>
                    @unless ($k->offen())<span class="badge gray">geschlossen</span>@endunless
                </div>
            @empty
                <p class="empty">Noch kein Treuhandkonto.</p>
            @endforelse

            <form wire:submit="kontoAnlegen" style="margin-top:12px;border-top:1px solid var(--line-cool);padding-top:12px">
                <p class="kicker">Neues Konto</p>
                <div class="field"><label>Bewohner:in</label>
                    <select wire:model="k_resident"><option value="">– wählen –</option>@foreach ($bewohner as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    @error('k_resident')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>IBAN Sonderkonto (optional)</label><input type="text" wire:model="k_iban" placeholder="DE.." /></div>
                <button class="btn btn-ghost btn-sm">+ Konto</button>
            </form>
        </div>

        <div>
            @if ($konto)
                <div class="card">
                    <div class="card-head"><h3>{{ $konto->resident?->name }}</h3>
                        <span class="badge {{ $saldo > 0 ? 'green' : 'gray' }}">Guthaben: {{ number_format($saldo, 2, ',', '.') }} €</span>
                    </div>

                    <form wire:submit="buchen" style="border:1px solid var(--line-cool);border-radius:8px;padding:12px">
                        <p class="kicker">Buchung</p>
                        <div class="form-row-3">
                            <div class="field"><label>Vorgang</label><select wire:model.live="b_vorgang">@foreach ($vorgaenge as $v)<option value="{{ $v->value }}">{{ $v->label() }}</option>@endforeach</select></div>
                            <div class="field"><label>Betrag (€)</label><input type="number" step="0.01" wire:model="b_betrag" />@error('b_betrag')<span class="err">{{ $message }}</span>@enderror</div>
                            <div class="field"><label>Datum</label><input type="date" wire:model="b_datum" />@error('b_datum')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        <div class="form-row-2">
                            <div class="field"><label>Kategorie @if ($b_vorgang === 'auszahlung')(Pflicht)@endif</label>
                                <select wire:model="b_kategorie"><option value="">– keine –</option>@foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select>
                                @error('b_kategorie')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field"><label>Beleg-Nr.</label><input type="text" wire:model="b_beleg_nr" placeholder="Quittung/Rechnung" /></div>
                        </div>
                        <div class="field"><label>Verwendungszweck (Pflicht)</label><input type="text" wire:model="b_zweck" placeholder="z. B. Friseurbesuch" />@error('b_zweck')<span class="err">{{ $message }}</span>@enderror</div>
                        @if ($b_vorgang === 'korrektur')
                            <div class="form-row-2">
                                <div class="field"><label>Bezugsbuchung (Nr.)</label><input type="number" wire:model="b_korrigiert_buchung_id" />@error('b_korrigiert_buchung_id')<span class="err">{{ $message }}</span>@enderror</div>
                                <div class="field"><label>Grund (Pflicht) — Betrag vorzeichenbehaftet</label><input type="text" wire:model="b_grund" />@error('b_grund')<span class="err">{{ $message }}</span>@enderror</div>
                            </div>
                        @endif
                        <button class="btn btn-primary btn-sm">Buchen</button>
                    </form>

                    <table class="data-table" style="margin-top:14px">
                        <thead><tr><th>Nr.</th><th>Datum</th><th>Vorgang</th><th>Zweck</th><th style="text-align:right">Betrag</th><th style="text-align:right">Saldo</th></tr></thead>
                        <tbody>
                            @forelse ($buchungen as $b)
                                <tr>
                                    <td>{{ $b->lfd_nr }}</td>
                                    <td>{{ $b->datum->format('d.m.Y') }}</td>
                                    <td>{{ $b->vorgang->label() }}@if ($b->kategorie)<br><span class="muted" style="font-size:.78em">{{ $b->kategorie->label() }}</span>@endif</td>
                                    <td>{{ $b->zweck }}@if ($b->beleg_nr)<br><span class="muted" style="font-size:.78em">Beleg {{ $b->beleg_nr }}</span>@endif@if ($b->grund)<br><span class="muted" style="font-size:.78em">{{ $b->grund }}</span>@endif</td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format((float) $b->betrag, 2, ',', '.') }}</td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums"><b>{{ number_format((float) $b->saldo_nach, 2, ',', '.') }}</b></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><p class="empty">Noch keine Buchung.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Budget-Setzungen</h3></div>
                    @foreach ($budgets as $bg)
                        @php($st = $budgetStatus[$bg->id] ?? null)
                        <div class="qm-anf" style="padding:5px 0;border-bottom:1px solid var(--line-cool)">
                            <b>{{ $bg->kategorie?->label() ?? 'Gesamtbudget' }}</b>
                            <span>Limit {{ number_format((float) $bg->limit_betrag, 2, ',', '.') }} € / Monat</span>
                            @if ($st)
                                <span class="badge {{ $st->ampel() === 'rot' ? 'red' : ($st->ampel() === 'gelb' ? 'amber' : 'green') }}">
                                    {{ number_format($st->verbraucht, 2, ',', '.') }} € · {{ $st->prozent() }}%
                                </span>
                            @endif
                            @if ($bg->sperre)<span class="badge red" title="Auszahlung über Limit wird blockiert">Sperre</span>@else<span class="badge gray">Warnung</span>@endif
                            <button class="btn btn-ghost btn-sm" wire:click="budgetLoeschen({{ $bg->id }})">entfernen</button>
                        </div>
                    @endforeach
                    <form wire:submit="budgetSetzen" style="margin-top:10px;border-top:1px solid var(--line-cool);padding-top:10px">
                        <div class="form-row-3">
                            <div class="field"><label>Kategorie</label>
                                <select wire:model="bg_kategorie"><option value="">Gesamtbudget</option>@foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select>
                            </div>
                            <div class="field"><label>Limit € / Monat</label><input type="number" step="0.01" wire:model="bg_limit" />@error('bg_limit')<span class="err">{{ $message }}</span>@enderror</div>
                            <div class="field"><label>Warnung ab %</label><input type="number" wire:model="bg_warn" />@error('bg_warn')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        <label class="check"><input type="checkbox" wire:model="bg_sperre" /> Harte Sperre (Auszahlung über Limit blockieren)</label>
                        <button class="btn btn-ghost btn-sm">Budget speichern</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Rechnungslegung (Monatsabschluss)</h3></div>
                    @foreach ($abschluesse as $a)
                        <div class="qm-anf" style="padding:5px 0">
                            <b>{{ $a->monat->isoFormat('MMMM YYYY') }}</b>
                            <span>Anfang {{ number_format((float) $a->anfangsbestand, 2, ',', '.') }} € · Ein {{ number_format((float) $a->summe_einzahlungen, 2, ',', '.') }} € · Aus {{ number_format((float) $a->summe_auszahlungen, 2, ',', '.') }} €</span>
                            <span class="badge green">Ende {{ number_format((float) $a->endbestand, 2, ',', '.') }} €</span>
                            <span class="muted">erstellt: {{ $a->erstellt_von }}</span>
                            @if ($a->gesperrt_am)<span class="badge gray">gesperrt</span>@endif
                        </div>
                    @endforeach
                    <form wire:submit="monatsabschluss" style="margin-top:10px;border-top:1px solid var(--line-cool);padding-top:10px">
                        <div class="form-row-2">
                            <div class="field"><label>Monat</label><input type="date" wire:model="ab_monat" /></div>
                            <div class="field"><label>Erstellt von</label><input type="text" wire:model="ab_erstellt_von" />@error('ab_erstellt_von')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        <button class="btn btn-primary btn-sm">Abschluss erstellen + sperren</button>
                    </form>
                    <p class="muted" style="margin-top:10px;font-size:.82em">Treuhänderische Verwaltung getrennt vom Einrichtungsvermögen, Einzelbelegpflicht und prüfungsfähige Aufzeichnung je Bewohner (HeimsicherungsV § 8/§ 15/§ 17). Buchungen sind unveränderbar; Korrekturen erfolgen als neue Korrekturbuchung.</p>
                </div>
            @else
                <div class="card"><p class="empty">Konto links wählen oder anlegen.</p></div>
            @endif
        </div>
    </div>
</div>
