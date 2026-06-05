<div>
    <a href="{{ route('bewohner') }}" class="back-link" wire:navigate>← Alle Bewohner:innen</a>
    <div class="page-head">
        <div>
            <p class="kicker">Bewohner:in</p>
            <h1>{{ $resident->name }}</h1>
            <p class="lead">
                Zimmer {{ $resident->room?->nummer ?? '—' }}
                @if ($resident->pflegegrad) · Pflegegrad {{ $resident->pflegegrad }} @endif
                · geb. {{ $resident->geburtsdatum?->format('d.m.Y') }}
                · aufgenommen {{ $resident->aufnahme_am?->format('d.m.Y') }}
            </p>
        </div>
        <div style="display:flex;gap:8px">
            <a href="{{ route('medikation.stellplan', $resident) }}" class="btn btn-primary" wire:navigate>💊 Medikation/Stellplan</a>
            <a href="{{ route('medikation.verordnungen', $resident) }}" class="btn" wire:navigate>Verordnungen</a>
            <a href="{{ route('medikation.vitalwerte', $resident) }}" class="btn" wire:navigate>Vitalwerte</a>
            <a href="{{ route('assessment.verlauf', $resident) }}" class="btn" wire:navigate>Assessments</a>
            <a href="{{ route('pflegeplanung') }}" class="btn btn-ghost">Im SIS-Board ansehen</a>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- ===================== STAMMDATEN ===================== --}}
    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3>Diagnosen (ICD-10)</h3></div>
            @forelse ($resident->diagnoses as $d)
                <div class="chip"><b>{{ $d->icdCode->code }}</b> {{ $d->icdCode->bezeichnung }} <span class="badge {{ $d->art === 'primär' ? 'green' : 'gray' }}" style="margin-left:auto">{{ $d->art }}</span></div>
            @empty <p class="empty">Keine Diagnosen erfasst.</p> @endforelse
            <form wire:submit="addDiagnosis" style="margin-top:14px">
                <div class="form-row">
                    <div class="field" style="position:relative"><label>ICD-Code suchen</label>
                        @if ($diag_icd)
                            <div class="chip" style="background:var(--c-surface-2)">
                                <b>{{ $diag_label }}</b>
                                <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="$set('diag_icd', null)" title="Auswahl löschen">✕</button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.300ms="diag_search" placeholder="Code (z. B. I10) oder Text (z. B. Demenz)…" autocomplete="off" />
                            @if (mb_strlen(trim($diag_search)) >= 2)
                                <ul class="typeahead" style="list-style:none;margin:4px 0 0;padding:0;max-height:240px;overflow:auto;border:1px solid var(--c-border);border-radius:var(--radius)">
                                    @forelse ($diagnosisResults as $c)
                                        <li><button type="button" class="typeahead-item" style="display:block;width:100%;text-align:left;padding:6px 10px;background:none;border:0;cursor:pointer"
                                            wire:click="selectDiagnosis({{ $c->id }})"><b>{{ $c->code }}</b> — {{ $c->bezeichnung }}</button></li>
                                    @empty
                                        <li style="padding:6px 10px;color:var(--c-muted)">Kein Treffer.</li>
                                    @endforelse
                                </ul>
                            @endif
                        @endif
                        @error('diag_icd')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field"><label>Art</label>
                        <select wire:model="diag_art"><option value="primär">primär</option><option value="sekundär">sekundär</option></select>
                    </div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Diagnose</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Krankenkassen</h3></div>
            @forelse ($resident->insurances as $i)
                <div class="chip"><b>{{ $i->healthInsurance->name }}</b> {{ $i->versichertennr }} @if ($i->ist_primaer)<span class="badge green" style="margin-left:auto">primär</span>@endif</div>
            @empty <p class="empty">Keine Kasse erfasst.</p> @endforelse
            <form wire:submit="addInsurance" style="margin-top:14px">
                <div class="form-row">
                    <div class="field"><label>Kasse</label>
                        <select wire:model="ins_id"><option value="">— wählen —</option>
                            @foreach ($insurances as $ins)<option value="{{ $ins->id }}">{{ $ins->name }}</option>@endforeach
                        </select>@error('ins_id')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field"><label>Versichertennr.</label><input type="text" wire:model="ins_nr" /></div>
                </div>
                <label style="display:flex;gap:8px;align-items:center;font-size:0.85em;margin-bottom:10px"><input type="checkbox" wire:model="ins_primary" style="width:auto" /> primäre Kasse</label>
                <button class="btn btn-ghost btn-sm">+ Kasse</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Betreuer:innen</h3></div>
            @forelse ($resident->custodians as $c)
                <div class="chip"><b>{{ $c->name }}</b> @if ($c->umfang)· {{ $c->umfang }}@endif @if ($c->kontakt)<span class="muted" style="margin-left:auto">{{ $c->kontakt }}</span>@endif</div>
            @empty <p class="empty">Keine Betreuung erfasst.</p> @endforelse
            <form wire:submit="addCustodian" style="margin-top:14px">
                <div class="field"><label>Name</label><input type="text" wire:model="cust_name" />@error('cust_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="form-row">
                    <div class="field"><label>Umfang</label><input type="text" wire:model="cust_umfang" placeholder="z. B. Gesundheitsfürsorge" /></div>
                    <div class="field"><label>Kontakt</label><input type="text" wire:model="cust_kontakt" /></div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Betreuer:in</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Behandelnde Ärzt:innen</h3></div>
            @forelse ($resident->physicians as $p)
                <div class="chip"><b>{{ $p->name }}</b> @if ($p->fachrichtung)· {{ $p->fachrichtung }}@endif</div>
            @empty <p class="empty">Keine Ärzt:innen zugeordnet.</p> @endforelse
            <form wire:submit="attachPhysician" style="margin-top:14px">
                <div class="field"><label>Arzt/Ärztin</label>
                    <select wire:model="phys_id"><option value="">— wählen —</option>
                        @foreach ($physicians as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->fachrichtung }})</option>@endforeach
                    </select>@error('phys_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-ghost btn-sm">+ Zuordnen</button>
            </form>
        </div>
    </div>

    {{-- ===================== SIS ===================== --}}
    <div class="card">
        <div class="card-head"><h3>SIS®-Informationssammlung</h3></div>
        @php $sis = $resident->sisAssessments->first(); @endphp
        @if ($sis)
            <p class="muted" style="margin-top:0">Aktive Erhebung v{{ $sis->version }} vom {{ $sis->erstellt_am?->format('d.m.Y') }}@if ($sis->eingangsfrage) · „{{ $sis->eingangsfrage }}"@endif</p>
            <div class="chip-list">
                @foreach ($sis->topicFields as $tf)
                    <div class="chip"><b>{{ $tf->themenfeld->label() }}:</b> {{ $tf->freitext }}</div>
                @endforeach
                @if ($sis->riskItems->isNotEmpty())
                    <div class="chip">Risiken: @foreach ($sis->riskItems as $ri)<span class="badge red" style="margin-right:4px">{{ ucfirst($ri->risiko->value) }}</span>@endforeach</div>
                @endif
            </div>
        @else
            <p class="empty">Noch keine SIS-Erhebung. Lege unten die erste an.</p>
        @endif

        <div class="section-label" style="margin-top:18px">Neue SIS-Erhebung anlegen</div>
        <form wire:submit="createSis">
            <x-voice-field model="sis_eingangsfrage" label="Eingangsfrage / Sichtweise der/des Pflegebedürftigen" :rows="2" />
            @foreach ($topicFields as $f)
                <x-voice-field wire:key="vf-{{ $f->value }}" model="sis_felder.{{ $f->value }}" :label="$f->label()" :context="$f->label()" :rows="2" />
            @endforeach
            <div class="field"><label>Eingeschätzte Risiken</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    @foreach ($riskTypes as $rt)
                        <label style="display:flex;gap:6px;align-items:center;font-size:0.88em"><input type="checkbox" wire:model="sis_risiken" value="{{ $rt->value }}" style="width:auto" /> {{ ucfirst($rt->value) }}</label>
                    @endforeach
                </div>
            </div>
            <button class="btn btn-primary">SIS anlegen</button>
        </form>
    </div>

    {{-- ===================== MASSNAHMEN ===================== --}}
    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3>Maßnahmenplan</h3></div>
            @forelse ($resident->careMeasures as $m)
                <div class="chip"><div><b>{{ $m->themenfeld->label() }}</b><br>{{ $m->beschreibung }}@if ($m->ziel)<br><span class="muted">Ziel: {{ $m->ziel }}</span>@endif</div></div>
            @empty <p class="empty">Keine Maßnahmen geplant.</p> @endforelse
            <form wire:submit="addMeasure" style="margin-top:14px">
                <div class="field"><label>Lebensbereich</label>
                    <select wire:model="m_themenfeld">@foreach ($topicFields as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach</select>
                </div>
                <div class="field" style="position:relative"><label>Aus Katalog übernehmen <span style="color:var(--c-muted);font-weight:400">(optional)</span></label>
                    <input type="text" wire:model.live.debounce.300ms="m_katalog_search" placeholder="Standard-Maßnahme suchen (z. B. Gehübungen, Dekub)…" autocomplete="off" />
                    @if (mb_strlen(trim($m_katalog_search)) >= 2)
                        <ul class="typeahead" style="list-style:none;margin:4px 0 0;padding:0;max-height:200px;overflow:auto;border:1px solid var(--c-border);border-radius:var(--radius)">
                            @forelse ($measureSuggestions as $item)
                                <li><button type="button" class="typeahead-item" style="display:block;width:100%;text-align:left;padding:6px 10px;background:none;border:0;cursor:pointer"
                                    wire:click="pickMeasure({{ $item->id }})">{{ $item->bezeichnung }}</button></li>
                            @empty
                                <li style="padding:6px 10px;color:var(--c-muted)">Kein Treffer.</li>
                            @endforelse
                        </ul>
                    @endif
                </div>
                <x-voice-field model="m_beschreibung" label="Maßnahme" :context="'Maßnahme'" :rows="2" />
                <div class="field"><label>Ziel</label><input type="text" wire:model="m_ziel" /></div>
                <button class="btn btn-ghost btn-sm">+ Maßnahme</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Evaluation</h3></div>
            <form wire:submit="addEvaluation">
                <div class="field"><label>Maßnahme</label>
                    <select wire:model="e_measure"><option value="">— wählen —</option>
                        @foreach ($measures as $m)<option value="{{ $m->id }}">{{ $m->beschreibung }}</option>@endforeach
                    </select>@error('e_measure')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="form-row">
                    <div class="field"><label>Zielerreichung</label>
                        <select wire:model="e_zielerreichung"><option value="erreicht">erreicht</option><option value="teilweise">teilweise</option><option value="nicht">nicht</option></select>
                    </div>
                    <div class="field"><label>Anlass</label><input type="text" wire:model="e_anlass" placeholder="z. B. Quartalsevaluation" /></div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Evaluation</button>
            </form>
        </div>
    </div>

    {{-- ===================== BERICHTE ===================== --}}
    <div class="card">
        <div class="card-head"><h3>Berichteblatt</h3></div>
        <form wire:submit="addReport">
            <div class="form-row-3">
                <div class="field"><label>Datum/Zeit</label><input type="datetime-local" wire:model="r_datum" />@error('r_datum')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Schicht</label><select wire:model="r_schicht"><option value="frueh">Früh</option><option value="spaet">Spät</option><option value="nacht">Nacht</option></select></div>
            </div>
            <x-voice-field model="r_text" label="Eintrag" :context="'Pflegebericht'" :rows="2" />
            <button class="btn btn-primary btn-sm">Bericht speichern</button>
        </form>
    </div>

    {{-- ===================== VORKOMMNISSE / QS-INDIKATOREN ===================== --}}
    <div class="card">
        <div class="card-head"><h3>Vorkommnisse &amp; QS-Indikatoren</h3></div>
        @forelse ($resident->careEvents as $ev)
            <div class="chip">
                <b>{{ $ev->indicator->label() }}</b>
                <span style="color:var(--c-muted)">{{ $ev->datum->format('d.m.Y') }}</span>
                @if ($ev->severity)<span class="badge badge-warn">{{ $ev->severity->label() }}</span>@endif
                @if (!empty($ev->details['stadium']))<span class="badge">Stadium {{ $ev->details['stadium'] }}</span>@endif
                @if (!empty($ev->details['stelle']))<span style="color:var(--c-muted)">{{ $ev->details['stelle'] }}</span>@endif
                @if (!empty($ev->details['notiz']))<span style="color:var(--c-muted)">· {{ $ev->details['notiz'] }}</span>@endif
                <span style="margin-left:auto">
                    @if ($ev->behoben_am)
                        <span class="badge badge-ok">behoben {{ $ev->behoben_am->format('d.m.Y') }}</span>
                    @else
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="resolveCareEvent({{ $ev->id }})">Als behoben markieren</button>
                    @endif
                </span>
            </div>
        @empty
            <p class="empty">Keine Vorkommnisse dokumentiert.</p>
        @endforelse

        @can('create', \App\Domains\Quality\Models\CareEvent::class)
            <form wire:submit="recordCareEvent" style="margin-top:14px">
                <div class="form-row-3">
                    <div class="field"><label>Indikator</label>
                        <select wire:model.live="ce_indicator">@foreach ($indicators as $i)<option value="{{ $i->value }}">{{ $i->label() }}</option>@endforeach</select>
                        @error('ce_indicator')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field"><label>Datum</label><input type="date" wire:model="ce_datum" />@error('ce_datum')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Schweregrad <span style="color:var(--c-muted);font-weight:400">(optional)</span></label>
                        <select wire:model="ce_severity"><option value="">—</option>@foreach ($severities as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach</select>
                    </div>
                </div>
                @if ($ce_indicator === 'dekubitus')
                    <div class="form-row-3" style="background:var(--c-surface-2);padding:8px;border-radius:var(--radius)">
                        <div class="field"><label>Dekubitus-Stadium *</label>
                            <select wire:model="ce_dek_stadium">
                                <option value="">—</option>
                                <option value="1">Kategorie 1 (Rötung)</option>
                                <option value="2">Kategorie 2 (Teilverlust Haut)</option>
                                <option value="3">Kategorie 3 (Vollständiger Hautverlust)</option>
                                <option value="4">Kategorie 4 (Gewebsnekrose)</option>
                            </select>@error('ce_dek_stadium')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field"><label>Beginn *</label><input type="date" wire:model="ce_dek_beginn" />@error('ce_dek_beginn')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Abgeheilt am</label><input type="date" wire:model="ce_dek_ende" />@error('ce_dek_ende')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                    <div class="field"><label>Körperstelle</label><input type="text" wire:model="ce_dek_stelle" placeholder="z. B. Steißbein, Ferse links" />@error('ce_dek_stelle')<span class="err">{{ $message }}</span>@enderror</div>
                @endif
                <div class="field"><label>Notiz <span style="color:var(--c-muted);font-weight:400">(optional)</span></label>
                    <input type="text" wire:model="ce_notiz" placeholder="z. B. Sturz im Bad, Folgen…" />
                    @error('ce_notiz')<span class="err">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-ghost btn-sm">+ Vorkommnis</button>
            </form>
        @endcan
    </div>
</div>
