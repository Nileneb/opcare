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
            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
                <a href="{{ route('fhir.export', $resident) }}" class="btn btn-ghost" title="FHIR-R4-Dokument (Vorstufe ePflegebericht)">⤓ FHIR-Export</a>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <nav class="section-nav" aria-label="Abschnitte">
        <a href="#stammdaten" wire:ignore>Stammdaten</a>
        <a href="#status" wire:ignore>Einschätzungen</a>
        <a href="#medizinprodukte" wire:ignore>Medizinprodukte</a>
        <a href="#angehoerige" wire:ignore>Angehörige</a>
        <a href="#versorgung" wire:ignore>Versorgung</a>
        <a href="#sis" wire:ignore>SIS®</a>
        <a href="#massnahmen" wire:ignore>Maßnahmen</a>
        <a href="#berichte" wire:ignore>Berichte</a>
        <a href="#vorkommnisse" wire:ignore>Vorkommnisse</a>
        <a href="#dokumente" wire:ignore>Dokumente</a>
    </nav>

    {{-- ===================== STAMMDATEN ===================== --}}
    <div class="grid-2">
        <div class="card scroll-target" id="stammdaten">
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
            <div class="card-head"><h3>Allergien & Unverträglichkeiten</h3></div>
            @forelse ($resident->allergies as $a)
                <div class="chip">
                    <b>{{ $a->substanz }}</b>
                    <span class="badge gray">{{ $a->kategorie }}</span>
                    @if ($a->reaktion)<span style="color:var(--c-muted)">— {{ $a->reaktion }}</span>@endif
                    @if ($a->kritikalitaet)<span class="badge {{ $a->kritikalitaet === 'hoch' ? 'red' : 'gray' }}">{{ $a->kritikalitaet }}</span>@endif
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="removeAllergy({{ $a->id }})" wire:confirm="Eintrag entfernen?" title="Entfernen">✕</button>
                </div>
            @empty <p class="empty">Keine Allergien/Unverträglichkeiten erfasst.</p> @endforelse
            @can('update', $resident)
                <form wire:submit="addAllergy" style="margin-top:14px">
                    <div class="form-row">
                        <div class="field"><label>Substanz *</label><input type="text" wire:model="alg_substanz" placeholder="z. B. Penicillin, Erdnuss" />@error('alg_substanz')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Art</label>
                            <select wire:model="alg_typ"><option value="allergie">Allergie</option><option value="unvertraeglichkeit">Unverträglichkeit</option></select>
                        </div>
                    </div>
                    <div class="form-row-3">
                        <div class="field"><label>Kategorie</label>
                            <select wire:model="alg_kategorie"><option value="medikament">Medikament</option><option value="nahrung">Nahrung</option><option value="umwelt">Umwelt</option><option value="biologisch">Biologisch</option></select>
                        </div>
                        <div class="field"><label>Kritikalität</label>
                            <select wire:model="alg_kritikalitaet"><option value="">—</option><option value="niedrig">niedrig</option><option value="hoch">hoch</option><option value="unbekannt">unbekannt</option></select>
                        </div>
                        <div class="field"><label>Reaktion</label><input type="text" wire:model="alg_reaktion" placeholder="z. B. Hautausschlag, Atemnot" />@error('alg_reaktion')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                    <button class="btn btn-ghost btn-sm">+ Allergie/Unverträglichkeit</button>
                </form>
            @endcan
        </div>

        <div class="card scroll-target" id="status">
            <div class="card-head"><h3>Pflegerische Einschätzungen</h3>
                <span class="badge ulb" title="Fließt in den ÜLB-FHIR-Export (Status-Beobachtungen)">→ ÜLB-Export</span>
            </div>
            @forelse ($resident->statusObservations->sortByDesc('erfasst_am') as $o)
                <div class="chip">
                    <b>{{ $statusCatalog[$o->typ]['label'] ?? $o->typ }}</b>
                    <span>{{ $o->wert_code ? ($statusCatalog[$o->typ]['options'][$o->wert_code] ?? $o->wert_code) : $o->wert_text }}@if ($o->wert_code && $o->wert_text) <span style="color:var(--c-muted)">(Anlage {{ $o->wert_text }})</span>@endif</span>
                    @if (! empty($statusCatalog[$o->typ]['section']))<span class="badge gray">{{ $statusCatalog[$o->typ]['section'] }}</span>@endif
                    <span style="color:var(--c-muted)">{{ optional($o->erfasst_am)->format('d.m.Y') }}</span>
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="removeStatusObservation({{ $o->id }})" wire:confirm="Eintrag entfernen?" title="Entfernen">✕</button>
                </div>
            @empty <p class="empty">Keine Einschätzungen erfasst.</p> @endforelse
            @can('update', $resident)
                <form wire:submit="addStatusObservation" style="margin-top:14px">
                    <div class="form-row">
                        <div class="field"><label>Merkmal</label>
                            <select wire:model.live="so_typ">
                                @foreach ($statusCatalog as $key => $def)<option value="{{ $key }}">{{ $def['label'] }} ({{ $def['section'] }})</option>@endforeach
                            </select>
                        </div>
                        <div class="field"><label>Wert</label>
                            @if (in_array(($statusCatalog[$so_typ]['kind'] ?? 'coded'), ['coded', 'boolean'], true))
                                <select wire:model="so_wert_code">
                                    <option value="">– wählen –</option>
                                    @foreach ($statusCatalog[$so_typ]['options'] as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                                </select>@error('so_wert_code')<span class="err">{{ $message }}</span>@enderror
                            @elseif (($statusCatalog[$so_typ]['kind'] ?? 'coded') === 'coded_insertion_date')
                                <select wire:model="so_wert_code">
                                    <option value="">– Art der Ableitung –</option>
                                    @foreach ($statusCatalog[$so_typ]['options'] as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                                </select>@error('so_wert_code')<span class="err">{{ $message }}</span>@enderror
                                <input type="date" wire:model="so_wert_text" title="Anlagedatum" style="margin-top:6px" />@error('so_wert_text')<span class="err">{{ $message }}</span>@enderror
                            @elseif (($statusCatalog[$so_typ]['kind'] ?? 'coded') === 'datetime')
                                <input type="datetime-local" wire:model="so_wert_text" />@error('so_wert_text')<span class="err">{{ $message }}</span>@enderror
                            @else
                                <input type="text" wire:model="so_wert_text" placeholder="z. B. ruhig, unauffällig" />@error('so_wert_text')<span class="err">{{ $message }}</span>@enderror
                            @endif
                        </div>
                    </div>
                    <button class="btn btn-ghost btn-sm">+ Einschätzung</button>
                </form>
            @endcan
        </div>

        <div class="card scroll-target" id="krankenhausaufenthalte">
            <div class="card-head"><h3>Krankenhausaufenthalte</h3>
                <span class="badge ulb" title="Fließt in den ÜLB-FHIR-Export (Sektion Krankenhausaufenthalt)">→ ÜLB-Export</span>
            </div>
            @forelse ($resident->hospitalStays->sortByDesc('ende') as $h)
                <div class="chip">
                    <b>bis {{ $h->ende->format('d.m.Y') }}</b>
                    @if ($h->grund)<span style="color:var(--c-muted)">— {{ $h->grund }}</span>@endif
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="removeHospitalStay({{ $h->id }})" wire:confirm="Eintrag entfernen?" title="Entfernen">✕</button>
                </div>
            @empty <p class="empty">Keine Krankenhausaufenthalte erfasst.</p> @endforelse
            @can('update', $resident)
                <form wire:submit="addHospitalStay" style="margin-top:14px">
                    <div class="form-row">
                        <div class="field"><label>Aufenthalt beendet am</label>
                            <input type="date" wire:model="hos_ende" />@error('hos_ende')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field"><label>Grund (intern, optional)</label>
                            <input type="text" wire:model="hos_grund" placeholder="z. B. Sturz mit Fraktur" />@error('hos_grund')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <button class="btn btn-ghost btn-sm">+ Aufenthalt</button>
                </form>
            @endcan
        </div>

        <div class="card scroll-target" id="empfehlungen">
            <div class="card-head"><h3>Empfehlungen an die aufnehmende Einrichtung</h3>
                <span class="badge ulb" title="Fließt in den ÜLB-FHIR-Export (Sektion Empfehlung)">→ ÜLB-Export</span>
            </div>
            @forelse ($resident->recommendations->sortByDesc('erstellt_am') as $r)
                <div class="chip">
                    <span>{{ $r->empfehlung }}</span>
                    @if ($r->erstellt_am)<span style="color:var(--c-muted)">{{ $r->erstellt_am->format('d.m.Y') }}</span>@endif
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="removeRecommendation({{ $r->id }})" wire:confirm="Eintrag entfernen?" title="Entfernen">✕</button>
                </div>
            @empty <p class="empty">Keine Empfehlungen erfasst.</p> @endforelse
            @can('update', $resident)
                <form wire:submit="addRecommendation" style="margin-top:14px">
                    <div class="field"><label>Empfehlung</label>
                        <textarea wire:model="rec_text" rows="2" placeholder="z. B. Weiterführung der Dekubitusprophylaxe alle 2 Stunden"></textarea>@error('rec_text')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <button class="btn btn-ghost btn-sm">+ Empfehlung</button>
                </form>
            @endcan
        </div>

        <div class="card scroll-target" id="medizinprodukte">
            <div class="card-head"><h3>Medizinprodukte & Hilfsmittel</h3>
                <span class="badge ulb" title="Fließt in den ÜLB-FHIR-Export (Sektion Medizinprodukte)">→ ÜLB-Export</span>
            </div>
            @forelse ($resident->devices as $d)
                <div class="chip">
                    <b>{{ $d->bezeichnung }}</b>
                    <span class="badge gray">{{ $d->kategorie }}</span>
                    @if ($d->hinweis)<span style="color:var(--c-muted)">— {{ $d->hinweis }}</span>@endif
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="removeDevice({{ $d->id }})" wire:confirm="Eintrag entfernen?" title="Entfernen">✕</button>
                </div>
            @empty <p class="empty">Keine Medizinprodukte/Hilfsmittel erfasst.</p> @endforelse
            @can('update', $resident)
                <form wire:submit="addDevice" style="margin-top:14px">
                    <div class="form-row">
                        <div class="field"><label>Bezeichnung *</label><input type="text" wire:model="dev_bezeichnung" placeholder="z. B. Rollator, Hörgerät, PEG-Sonde" />@error('dev_bezeichnung')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Kategorie</label>
                            <select wire:model="dev_kategorie"><option value="hilfsmittel">Hilfsmittel</option><option value="implantat">Implantat</option><option value="sonstiges">Sonstiges</option></select>
                        </div>
                    </div>
                    <div class="field"><label>Hinweis</label><input type="text" wire:model="dev_hinweis" placeholder="optional" />@error('dev_hinweis')<span class="err">{{ $message }}</span>@enderror</div>
                    <button class="btn btn-ghost btn-sm">+ Medizinprodukt</button>
                </form>
            @endcan
        </div>

        <div class="card scroll-target" id="angehoerige">
            <div class="card-head"><h3>Angehörige & Kontaktpersonen</h3>
                <span class="badge ulb" title="Fließt in den ÜLB-FHIR-Export (Patienten-Adressbuch)">→ ÜLB-Export</span>
            </div>
            @forelse ($resident->contacts as $c)
                <div class="chip">
                    <b>{{ $c->name }}</b>
                    @if ($c->beziehung)<span class="badge gray">{{ $c->beziehung }}</span>@endif
                    @if ($c->telefon)<span style="color:var(--c-muted)">☎ {{ $c->telefon }}</span>@endif
                    @if ($c->benachrichtigen)<span class="badge green">benachrichtigen</span>@endif
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="removeContact({{ $c->id }})" wire:confirm="Eintrag entfernen?" title="Entfernen">✕</button>
                </div>
            @empty <p class="empty">Keine Kontaktpersonen erfasst.</p> @endforelse
            @can('update', $resident)
                <form wire:submit="addContact" style="margin-top:14px">
                    <div class="form-row-3">
                        <div class="field"><label>Name *</label><input type="text" wire:model="con_name" placeholder="z. B. Anna Schneider" />@error('con_name')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Beziehung</label><input type="text" wire:model="con_beziehung" placeholder="z. B. Tochter" />@error('con_beziehung')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Telefon</label><input type="text" wire:model="con_telefon" placeholder="optional" />@error('con_telefon')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                    <label style="font-weight:400"><input type="checkbox" wire:model="con_benachrichtigen" /> im Notfall benachrichtigen</label>
                    <div><button class="btn btn-ghost btn-sm" style="margin-top:8px">+ Kontaktperson</button></div>
                </form>
            @endcan
        </div>

        <div class="card scroll-target" id="versorgung">
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
    <div class="card scroll-target" id="sis">
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
        <div class="card scroll-target" id="massnahmen">
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
    <div class="card scroll-target" id="berichte">
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
    <div class="card scroll-target" id="vorkommnisse">
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
                @if ($ce_indicator === 'sturz')
                    <div class="form-row-3" style="background:var(--c-surface-2);padding:8px;border-radius:var(--radius)">
                        <div class="field"><label>Häufigkeit *</label>
                            <select wire:model="ce_sturz_anzahl">
                                <option value="1">einmal</option>
                                <option value="2">mehrmals</option>
                            </select>@error('ce_sturz_anzahl')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field"><label>Sturzfolge (DAS-Feld 72)</label>
                            <label style="font-weight:400"><input type="checkbox" wire:model="ce_sturz_fraktur" /> Fraktur aufgetreten</label>
                        </div>
                    </div>
                @endif
                <div class="field"><label>Notiz <span style="color:var(--c-muted);font-weight:400">(optional)</span></label>
                    <input type="text" wire:model="ce_notiz" placeholder="z. B. Sturz im Bad, Folgen…" />
                    @error('ce_notiz')<span class="err">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-ghost btn-sm">+ Vorkommnis</button>
            </form>
        @endcan
    </div>

    @livewire('masterdata.resident-media', ['resident' => $resident], key('media-'.$resident->id))
</div>
