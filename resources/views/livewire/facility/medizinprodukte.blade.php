<div>
    <div class="page-head">
        <div><p class="kicker">Haustechnik · Medizinprodukte</p><h1>Medizinprodukte</h1>
            <p class="lead">Bestandsverzeichnis (§ 14) & Medizinproduktebuch (§ 13 MPBetreibV): Einweisungen, STK/MTK-Fristen, Vorkommnisse.</p></div>
        @if ($ueberfaellig > 0)
            <span class="badge red" title="überfällige STK/MTK">{{ $ueberfaellig }} Kontrolle(n) überfällig</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Bestandsverzeichnis</h3><span class="badge gray">§ 14 MPBetreibV</span></div>
        <table class="data">
            <thead><tr><th>Produkt</th><th>Hersteller / SN</th><th>Standort</th><th>Anlage</th><th>Nächste Kontrolle</th></tr></thead>
            <tbody>
                @forelse ($produkte as $m)
                    <tr wire:click="select({{ $m->id }})" style="cursor:pointer" @class(['is-active' => $produkt && $produkt->id === $m->id])>
                        <td>
                            <b>{{ $m->bezeichnung }}</b>@if ($m->typ)<span class="muted"> · {{ $m->typ }}</span>@endif
                            @unless ($m->aktiv())<span class="badge gray">außer Betrieb</span>@endunless
                        </td>
                        <td>{{ $m->hersteller ?? '—' }}@if ($m->seriennummer)<br><span class="muted">SN {{ $m->seriennummer }}</span>@endif</td>
                        <td>{{ $m->standort ?? '—' }}@if ($m->zuordnung)<br><span class="muted">{{ $m->zuordnung }}</span>@endif</td>
                        <td><span class="badge {{ $m->anlage->value === 'keine' ? 'gray' : 'amber' }}">{{ $m->anlage->label() }}</span></td>
                        <td>
                            @php $stk = $m->naechsteStk(); $mtk = $m->naechsteMtk(); @endphp
                            @if (! $m->anlage->brauchtMedizinproduktebuch())
                                <span class="muted">—</span>
                            @else
                                <span class="badge {{ $m->pruefAmpel() === 'red' ? 'red' : ($m->pruefAmpel() === 'amber' ? 'amber' : 'green') }}">
                                    @if ($stk) STK {{ $stk->format('m/Y') }} @endif
                                    @if ($mtk) MTK {{ $mtk->format('m/Y') }} @endif
                                    @if (! $stk && ! $mtk) offen @endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Medizinprodukte erfasst.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($darfVerwalten)
        <div class="card">
            <div class="card-head"><h3>Medizinprodukt aufnehmen</h3></div>
            <form wire:submit="anlegen">
                <div class="form-row-3">
                    <div class="field"><label>Bezeichnung / Art *</label><input type="text" wire:model="p_bezeichnung" placeholder="z. B. Blutdruckmessgerät" />@error('p_bezeichnung')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Typ</label><input type="text" wire:model="p_typ" /></div>
                    <div class="field"><label>Hersteller / Bevollmächtigter</label><input type="text" wire:model="p_hersteller" /></div>
                </div>
                <div class="form-row-3">
                    <div class="field"><label>Los-/Seriennummer</label><input type="text" wire:model="p_seriennummer" /></div>
                    <div class="field"><label>Inventarnummer</label><input type="text" wire:model="p_inventarnummer" /></div>
                    <div class="field"><label>Anschaffungsjahr</label><input type="number" min="1950" wire:model="p_anschaffungsjahr" />@error('p_anschaffungsjahr')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <div class="form-row-3">
                    <div class="field"><label>Standort</label><input type="text" wire:model="p_standort" placeholder="z. B. Pflegezimmer 12" /></div>
                    <div class="field"><label>Betriebliche Zuordnung</label><input type="text" wire:model="p_zuordnung" placeholder="z. B. Wohnbereich Nord" /></div>
                    <div class="field"><label>Anlage (MPBetreibV)</label><select wire:model="p_anlage">@foreach ($anlagen as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach</select></div>
                </div>
                <div class="field" style="max-width:240px"><label>Inbetriebnahme</label><input type="date" wire:model="p_inbetriebnahme" /></div>
                <button class="btn btn-primary btn-sm">+ Ins Bestandsverzeichnis</button>
            </form>
        </div>
    @endif

    @if ($produkt)
        <div class="card">
            <div class="card-head">
                <h3>{{ $produkt->bezeichnung }}</h3>
                <span class="badge {{ $produkt->anlage->value === 'keine' ? 'gray' : 'amber' }}">{{ $produkt->anlage->label() }}</span>
            </div>

            <div class="grid-3">
                <div><span class="muted">Typ</span><br>{{ $produkt->typ ?? '—' }}</div>
                <div><span class="muted">Hersteller</span><br>{{ $produkt->hersteller ?? '—' }}</div>
                <div><span class="muted">Seriennummer</span><br>{{ $produkt->seriennummer ?? '—' }}</div>
                <div><span class="muted">Inventarnummer</span><br>{{ $produkt->inventarnummer ?? '—' }}</div>
                <div><span class="muted">Anschaffungsjahr</span><br>{{ $produkt->anschaffungsjahr ?? '—' }}</div>
                <div><span class="muted">Standort / Zuordnung</span><br>{{ $produkt->standort ?? '—' }}@if ($produkt->zuordnung) · {{ $produkt->zuordnung }}@endif</div>
                <div><span class="muted">Inbetriebnahme</span><br>{{ $produkt->inbetriebnahme_am?->format('d.m.Y') ?? '—' }}</div>
            </div>

            @if ($darfVerwalten)
                <div style="display:flex;gap:8px;margin-top:8px">
                    @if ($produkt->aktiv())
                        <button class="btn btn-ghost btn-sm" wire:click="ausserBetrieb" wire:confirm="Außer Betrieb nehmen?">Außer Betrieb nehmen</button>
                    @else
                        <button class="btn btn-ghost btn-sm" wire:click="wiederInBetrieb">Wieder in Betrieb</button>
                    @endif
                </div>
            @endif
        </div>

        @if ($produkt->anlage->brauchtMedizinproduktebuch())
            <div class="card">
                <div class="card-head"><h3>Prüffristen (Medizinproduktebuch § 13)</h3></div>
                <table class="data">
                    <thead><tr><th>Kontrolle</th><th>Pflicht</th><th>Letzte</th><th>Nächste</th>@if ($darfVerwalten)<th></th>@endif</tr></thead>
                    <tbody>
                        <tr>
                            <td><b>STK</b> <span class="muted">sicherheitstechnisch (§ 12)</span></td>
                            <td>{{ $produkt->anlage->brauchtStk() ? ($produkt->stk_intervall_monate ?? 24).' Monate' : '—' }}</td>
                            <td>{{ $produkt->letzte_stk?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                @php $stk = $produkt->naechsteStk(); @endphp
                                @if ($stk)<span class="badge {{ $stk->isPast() ? 'red' : ($stk->lte(now()->addDays(30)) ? 'amber' : 'green') }}">{{ $stk->format('d.m.Y') }}</span>
                                @elseif ($produkt->anlage->brauchtStk())<span class="badge amber">offen</span>@else —@endif
                            </td>
                            @if ($darfVerwalten)<td>@if ($produkt->anlage->brauchtStk())
                                <div style="display:flex;gap:4px"><input type="date" wire:model="stk_datum" /><button class="btn btn-ghost btn-sm" wire:click="stkDokumentieren">dokumentieren</button></div>
                                @error('stk_datum')<span class="err">{{ $message }}</span>@enderror
                            @endif</td>@endif
                        </tr>
                        <tr>
                            <td><b>MTK</b> <span class="muted">messtechnisch (§ 15)</span></td>
                            <td>{{ $produkt->anlage->brauchtMtk() ? ($produkt->mtk_intervall_monate ? $produkt->mtk_intervall_monate.' Monate' : 'lt. Anlage 2') : '—' }}</td>
                            <td>{{ $produkt->letzte_mtk?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                @php $mtk = $produkt->naechsteMtk(); @endphp
                                @if ($mtk)<span class="badge {{ $mtk->isPast() ? 'red' : ($mtk->lte(now()->addDays(30)) ? 'amber' : 'green') }}">{{ $mtk->format('d.m.Y') }}</span>
                                @elseif ($produkt->anlage->brauchtMtk())<span class="badge amber">offen</span>@else —@endif
                            </td>
                            @if ($darfVerwalten)<td>@if ($produkt->anlage->brauchtMtk())
                                <div style="display:flex;gap:4px"><input type="date" wire:model="mtk_datum" /><button class="btn btn-ghost btn-sm" wire:click="mtkDokumentieren">dokumentieren</button></div>
                                @error('mtk_datum')<span class="err">{{ $message }}</span>@enderror
                            @endif</td>@endif
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card">
            <div class="card-head"><h3>Eingewiesene Personen</h3><span class="badge gray">§ 4 / § 11</span></div>
            @forelse ($einweisungen as $e)
                <div class="qm-anf">
                    <span class="badge green">{{ $e->art === 'folgeeinweisung' ? 'Folge' : 'Erst' }}</span>
                    <b>{{ $e->user?->name }}</b>
                    <span class="muted">· {{ $e->eingewiesen_am?->format('d.m.Y') }}@if ($e->eingewiesen_durch) durch {{ $e->eingewiesen_durch }}@endif</span>
                </div>
            @empty
                <p class="empty">Noch keine Einweisung dokumentiert — nur eingewiesene Personen dürfen das Produkt benutzen.</p>
            @endforelse

            @if ($darfVerwalten)
                <form wire:submit="einweisen" style="margin-top:12px">
                    <div class="form-row-3">
                        <div class="field"><label>Person *</label><select wire:model="e_user"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>@error('e_user')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Datum *</label><input type="date" wire:model="e_datum" /></div>
                        <div class="field"><label>Art</label><select wire:model="e_art"><option value="ersteinweisung">Ersteinweisung</option><option value="folgeeinweisung">Folgeeinweisung</option></select></div>
                    </div>
                    <div class="field"><label>Eingewiesen durch</label><input type="text" wire:model="e_durch" placeholder="z. B. Medizintechniker:in / Hersteller" /></div>
                    <button class="btn btn-ghost btn-sm">+ Einweisung</button>
                </form>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Funktionsstörungen & Vorkommnisse</h3><span class="badge gray">§ 13 · BfArM</span></div>
            @forelse ($vorkommnisse as $v)
                <div class="qm-item">
                    <div class="qm-anf">
                        <span class="badge {{ $v->art->meldepflichtig() ? 'red' : 'amber' }}">{{ $v->art->label() }}</span>
                        @if ($v->offen())<span class="badge amber">offen</span>@else<span class="badge green">behoben {{ $v->behoben_am?->format('d.m.Y') }}</span>@endif
                        @if ($v->art->meldepflichtig())<span class="badge {{ $v->bfarm_gemeldet ? 'green' : 'red' }}">{{ $v->bfarm_gemeldet ? 'BfArM gemeldet' : 'BfArM-Meldung offen' }}</span>@endif
                        <span class="muted" style="margin-left:auto">{{ $v->melder?->name }} · {{ $v->datum?->format('d.m.Y') }}</span>
                    </div>
                    <p class="muted" style="margin:2px 0 6px">{{ $v->beschreibung }}@if ($v->massnahme)<br><b>Maßnahme:</b> {{ $v->massnahme }}@endif</p>
                    @if ($darfVerwalten)
                        <div style="display:flex;gap:8px">
                            @if ($v->offen())<button class="btn btn-ghost btn-sm" wire:click="vorkommnisBehoben({{ $v->id }})">behoben</button>@endif
                            @if ($v->art->meldepflichtig() && ! $v->bfarm_gemeldet)<button class="btn btn-ghost btn-sm" wire:click="bfarmGemeldet({{ $v->id }})">BfArM gemeldet</button>@endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="empty">Keine Vorkommnisse erfasst.</p>
            @endforelse

            @if ($darfVerwalten)
                <form wire:submit="vorkommnisMelden" style="margin-top:12px">
                    <div class="form-row-3">
                        <div class="field"><label>Art</label><select wire:model="v_art">@foreach ($vorkommnisArten as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach</select></div>
                        <div class="field"><label>Datum *</label><input type="date" wire:model="v_datum" /></div>
                    </div>
                    <div class="field"><label>Beschreibung *</label><textarea wire:model="v_beschreibung" rows="2"></textarea>@error('v_beschreibung')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Maßnahme</label><textarea wire:model="v_massnahme" rows="2"></textarea></div>
                    <button class="btn btn-primary btn-sm">Vorkommnis erfassen</button>
                </form>
            @endif
        </div>
    @endif
</div>
