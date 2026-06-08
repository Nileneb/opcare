<div>
    <div class="page-head">
        <div>
            <p class="kicker">Betrieb · Brandschutz</p>
            <h1>Brandschutz</h1>
            <p class="lead">Brandschutzorganisation nach § 10 ArbSchG, ASR A2.2/A2.3, DIN 14096: Brandschutzordnung (Dokument + Revisions-Ampel), Begehungsprotokolle mit Mängel-Workflow und Räumungsübungs-Nachweise mit Frist-Ampel.</p>
        </div>
        @if ($ueberfaellig > 0)
            <span class="badge red" title="überfällige Elemente">{{ $ueberfaellig }} überfällig</span>
        @endif
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- 1. Brandschutzordnung                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="card">
        <div class="card-head"><h3>Brandschutzordnung (DIN 14096)</h3><span class="badge gray">DIN 14096</span></div>
        <form wire:submit="ordnungAnlegen">
            <div class="form-row-3">
                <div class="field">
                    <label>Titel *</label>
                    <input type="text" wire:model="ordnung_titel" placeholder="z. B. Brandschutzordnung Teil B 2024" />
                    @error('ordnung_titel')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Teil (DIN 14096) *</label>
                    <select wire:model="ordnung_teil">
                        <option value="">— bitte wählen —</option>
                        @foreach ($teile as $teil)
                            <option value="{{ $teil->value }}">{{ $teil->label() }}</option>
                        @endforeach
                    </select>
                    @error('ordnung_teil')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Version *</label>
                    <input type="text" wire:model="ordnung_version" placeholder="z. B. 2024-01" />
                    @error('ordnung_version')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Revisionsintervall (Monate) *</label>
                    <input type="number" wire:model="ordnung_revision_intervall_monate" min="1" max="120" />
                    @error('ordnung_revision_intervall_monate')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Brandschutzordnung anlegen</button>
        </form>
    </div>

    @forelse ($ordnungen as $ordnung)
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>{{ $ordnung->titel }}</h3>
                    <span class="muted">{{ $ordnung->teil->label() }} · Version {{ $ordnung->version }}</span>
                    @if ($ordnung->freigeber)
                        <span class="muted"> · Freigegeben von {{ $ordnung->freigeber->name }} am {{ $ordnung->freigegeben_am->format('d.m.Y') }}</span>
                    @endif
                </div>
                @php $ampel = $ordnung->ampel(); @endphp
                @if ($ampel === 'red')
                    <span class="badge red">{{ $ordnung->status() === 'entwurf' ? 'Entwurf' : 'Revision überfällig' }}</span>
                @elseif ($ampel === 'amber')
                    <span class="badge amber">Revision fällig in &lt; 30 Tagen</span>
                @else
                    <span class="badge green">Revision aktuell</span>
                @endif
            </div>

            <div class="grid-3" style="margin-bottom:.75rem">
                <div>
                    <span class="muted">Zielgruppe</span><br>
                    {{ $ordnung->teil->zielgruppe() }}
                </div>
                <div>
                    <span class="muted">Nächste Revision</span><br>
                    {{ $ordnung->naechsteRevision()?->format('d.m.Y') ?? 'noch nicht freigegeben' }}
                </div>
                <div>
                    <span class="muted">Revisionsintervall</span><br>
                    {{ $ordnung->revision_intervall_monate }} Monate
                </div>
            </div>

            @if ($ordnung->status() === 'entwurf')
                <form wire:submit="ordnungFreigeben({{ $ordnung->id }})">
                    <button class="btn btn-primary btn-sm">Freigeben (heute)</button>
                </form>
            @endif
        </div>
    @empty
        <div class="card"><p class="muted">Noch keine Brandschutzordnung erfasst. Bitte oben eine Ordnung anlegen.</p></div>
    @endforelse

    {{-- ------------------------------------------------------------------ --}}
    {{-- 2. Brandschutzbegehungen                                            --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="card" style="margin-top:1.5rem">
        <div class="card-head"><h3>Brandschutzbegehungen</h3><span class="badge gray">DGUV 205-001</span></div>
        <form wire:submit="begehungErfassen">
            <div class="form-row-3">
                <div class="field">
                    <label>Bereich *</label>
                    <input type="text" wire:model="begehung_bereich" placeholder="z. B. Wohnbereich 1, Küche, Keller" />
                    @error('begehung_bereich')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Begangen am *</label>
                    <input type="date" wire:model="begehung_begangen_am" />
                    @error('begehung_begangen_am')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Intervall (Monate) *</label>
                    <input type="number" wire:model="begehung_intervall_monate" min="1" max="120" />
                    @error('begehung_intervall_monate')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Bemerkung</label>
                    <input type="text" wire:model="begehung_bemerkung" placeholder="Optionale Bemerkung" />
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Begehung erfassen</button>
        </form>
    </div>

    @forelse ($begehungen as $begehung)
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>{{ $begehung->bereich }}</h3>
                    <span class="muted">Begangen am {{ $begehung->begangen_am->format('d.m.Y') }}</span>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    @php $fs = $begehung->faelligkeitsStatus(); @endphp
                    @if ($fs === 'rot')
                        <span class="badge red">Begehung überfällig</span>
                    @elseif ($fs === 'gelb')
                        <span class="badge amber">Begehung fällig in &lt; 30 Tagen</span>
                    @else
                        <span class="badge green">Begehung aktuell</span>
                    @endif

                    @if ($begehung->hatOffeneMaengel())
                        <span class="badge red">{{ $begehung->offeneMaengel()->count() }} offene Mängel</span>
                        @if ($begehung->hoechsteOffeneSchwere())
                            <span class="badge {{ $begehung->hoechsteOffeneSchwere()->ampel() }}">höchste: {{ $begehung->hoechsteOffeneSchwere()->label() }}</span>
                        @endif
                    @else
                        <span class="badge green">keine offenen Mängel</span>
                    @endif
                </div>
            </div>

            <div class="grid-3" style="margin-bottom:.75rem">
                <div>
                    <span class="muted">Nächste Begehung</span><br>
                    {{ $begehung->naechsteBegehung()->format('d.m.Y') }}
                </div>
                <div>
                    <span class="muted">Intervall</span><br>
                    {{ $begehung->intervall_monate }} Monate
                </div>
                @if ($begehung->bemerkung)
                    <div>
                        <span class="muted">Bemerkung</span><br>
                        {{ $begehung->bemerkung }}
                    </div>
                @endif
            </div>

            {{-- Mängel-Liste --}}
            @if ($begehung->maengel->isNotEmpty())
                <div style="margin-bottom:1rem">
                    <h4>Mängel</h4>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Beschreibung</th>
                                <th>Schwere</th>
                                <th>Frist</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($begehung->maengel as $mangel)
                                <tr>
                                    <td>{{ $mangel->beschreibung }}</td>
                                    <td><span class="badge {{ $mangel->schwere->ampel() }}">{{ $mangel->schwere->label() }}</span></td>
                                    <td>{{ $mangel->frist?->format('d.m.Y') ?? '—' }}</td>
                                    <td>
                                        @if ($mangel->istOffen())
                                            <span class="badge amber">offen</span>
                                        @else
                                            <span class="badge green">behoben {{ $mangel->behoben_am->format('d.m.Y') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($mangel->istOffen())
                                            <form wire:submit="mangelBehoben({{ $mangel->id }})" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
                                                <div class="field" style="margin:0">
                                                    <input type="date" wire:model="behoben_am" />
                                                    @error('behoben_am')<span class="err">{{ $message }}</span>@enderror
                                                </div>
                                                <div class="field" style="margin:0">
                                                    <input type="text" wire:model="behoben_notiz" placeholder="Notiz (optional)" />
                                                </div>
                                                <button class="btn btn-sm btn-primary">behoben</button>
                                            </form>
                                        @else
                                            <span class="muted">{{ $mangel->behoben_notiz ?? '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Mangel hinzufügen --}}
            <details>
                <summary class="btn btn-sm" style="cursor:pointer;display:inline-block">+ Mangel hinzufügen</summary>
                <form wire:submit="mangelHinzufuegen({{ $begehung->id }})" style="margin-top:.5rem">
                    <div class="form-row-3">
                        <div class="field">
                            <label>Beschreibung *</label>
                            <input type="text" wire:model="mangel_beschreibung" placeholder="z. B. Feuertür klemmt, Fluchtwegbeschilderung fehlt" />
                            @error('mangel_beschreibung')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Schwere *</label>
                            <select wire:model="mangel_schwere">
                                <option value="">— bitte wählen —</option>
                                @foreach ($schweren as $schwere)
                                    <option value="{{ $schwere->value }}">{{ $schwere->label() }}</option>
                                @endforeach
                            </select>
                            @error('mangel_schwere')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Frist</label>
                            <input type="date" wire:model="mangel_frist" />
                            @error('mangel_frist')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Mangel hinzufügen</button>
                </form>
            </details>
        </div>
    @empty
        <div class="card"><p class="muted">Noch keine Begehungen erfasst. Bitte oben eine Begehung anlegen.</p></div>
    @endforelse

    {{-- ------------------------------------------------------------------ --}}
    {{-- 3. Räumungsübungen                                                  --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="card" style="margin-top:1.5rem">
        <div class="card-head"><h3>Räumungsübungen</h3><span class="badge gray">§ 10 ArbSchG / ASR A2.3</span></div>
        <form wire:submit="uebungDokumentieren">
            <div class="form-row-3">
                <div class="field">
                    <label>Durchgeführt am *</label>
                    <input type="date" wire:model="uebung_durchgefuehrt_am" />
                    @error('uebung_durchgefuehrt_am')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Intervall (Monate) *</label>
                    <input type="number" wire:model="uebung_intervall_monate" min="1" max="120" />
                    @error('uebung_intervall_monate')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Bereich</label>
                    <input type="text" wire:model="uebung_bereich" placeholder="z. B. Wohnbereich 1, gesamtes Gebäude" />
                </div>
                <div class="field">
                    <label>Szenario</label>
                    <input type="text" wire:model="uebung_szenario" placeholder="z. B. Küchenbrand, Alarmsystem-Test" />
                </div>
                <div class="field">
                    <label>Teilnehmer</label>
                    <input type="number" wire:model="uebung_teilnehmer_anzahl" min="0" placeholder="Anzahl" />
                    @error('uebung_teilnehmer_anzahl')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Dauer (Minuten)</label>
                    <input type="number" wire:model="uebung_dauer_minuten" min="0" placeholder="Minuten" />
                    @error('uebung_dauer_minuten')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Erkenntnisse</label>
                    <input type="text" wire:model="uebung_erkenntnisse" placeholder="Wichtige Erkenntnisse und Verbesserungen" />
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Räumungsübung dokumentieren</button>
        </form>
    </div>

    @forelse ($uebungen as $uebung)
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Räumungsübung {{ $uebung->durchgefuehrt_am->format('d.m.Y') }}</h3>
                    @if ($uebung->bereich)<span class="muted">{{ $uebung->bereich }}</span>@endif
                </div>
                @php $ufs = $uebung->faelligkeitsStatus(); @endphp
                @if ($ufs === 'rot')
                    <span class="badge red">Nächste Übung überfällig</span>
                @elseif ($ufs === 'gelb')
                    <span class="badge amber">Nächste Übung in &lt; 30 Tagen</span>
                @else
                    <span class="badge green">Nächste Übung aktuell</span>
                @endif
            </div>

            <div class="grid-3" style="margin-bottom:.5rem">
                <div>
                    <span class="muted">Nächste Übung</span><br>
                    {{ $uebung->naechsteUebung()->format('d.m.Y') }}
                </div>
                @if ($uebung->szenario)
                    <div>
                        <span class="muted">Szenario</span><br>
                        {{ $uebung->szenario }}
                    </div>
                @endif
                @if ($uebung->teilnehmer_anzahl !== null || $uebung->dauer_minuten !== null)
                    <div>
                        @if ($uebung->teilnehmer_anzahl !== null)
                            <span class="muted">Teilnehmer:</span> {{ $uebung->teilnehmer_anzahl }}&nbsp;
                        @endif
                        @if ($uebung->dauer_minuten !== null)
                            <span class="muted">Dauer:</span> {{ $uebung->dauer_minuten }} min
                        @endif
                    </div>
                @endif
            </div>
            @if ($uebung->erkenntnisse)
                <p style="margin-top:.25rem"><span class="muted">Erkenntnisse:</span> {{ $uebung->erkenntnisse }}</p>
            @endif
        </div>
    @empty
        <div class="card"><p class="muted">Noch keine Räumungsübungen dokumentiert. Bitte oben eine Übung anlegen.</p></div>
    @endforelse

    <p class="muted" style="font-size:.8em;margin-top:1.5rem">
        Rechtsgrundlagen: § 10 ArbSchG (Brandbekämpfung und Evakuierung), ArbStättV Anhang 2.2/2.3,
        ASR A2.2 (Maßnahmen gegen Brände), ASR A2.3 (Fluchtwege und Notausgänge, Räumungsübungen),
        DIN 14096 (Brandschutzordnung Teil A/B/C), DGUV Information 205-001 (betrieblicher Brandschutz).
        Brandschutz-Technikprüfungen (Feuerlöscher, BMA): siehe Wartungsplan. Brandschutzhelfer-Ausbildung: siehe Arbeitsschutz-Nachweise.
    </p>
</div>
