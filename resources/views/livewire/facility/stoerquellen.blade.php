<div>
    <div class="page-head">
        <div>
            <p class="kicker">Haustechnik · Notfallvorsorge</p>
            <h1>Top-Störquellen &amp; Notfallvorsorge</h1>
            <p class="lead">Die häufigsten Ausfälle aus den Mängelmeldungen — mit Mindest-Ersatzteilen,
                fixierten Dienstleister-Reaktionszeiten und Sofortmaßnahmen je Störquelle.</p>
        </div>
        <a href="{{ route('haustechnik') }}" class="btn btn-ghost btn-sm">← zur Haustechnik</a>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Auswertungs-Fenster + Lücken-Hinweis --}}
    <div class="card">
        <div class="card-head">
            <h3>Auswertung der letzten {{ $monate }} Monate</h3>
            <div style="display:flex;gap:6px;align-items:center">
                <button class="btn btn-sm {{ $monate === 6 ? 'btn-primary' : 'btn-ghost' }}" wire:click="setFenster(6)">6 Monate</button>
                <button class="btn btn-sm {{ $monate === 12 ? 'btn-primary' : 'btn-ghost' }}" wire:click="setFenster(12)">12 Monate</button>
            </div>
        </div>

        @if ($luecken > 0)
            <div class="alert alert-danger" style="margin-bottom:10px">
                <b>{{ $luecken }}</b> der Top-Störquellen {{ $luecken === 1 ? 'hat' : 'haben' }} noch <b>keine hinterlegte Notfallvorsorge</b>
                — Ersatzteile, Reaktionszeit und Sofortmaßnahmen fehlen.
            </div>
        @endif

        @if ($top->isEmpty())
            <p class="empty">Im gewählten Zeitraum gibt es keine Mängelmeldungen.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Störquelle</th>
                        <th>Kategorie</th>
                        <th>Meldungen</th>
                        <th>offen</th>
                        <th>dringend</th>
                        <th>letzte</th>
                        <th>Vorsorge</th>
                        @if ($darfVerwalten)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($top as $i => $b)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><b>{{ $b->bezeichnung }}</b></td>
                            <td><span class="muted">{{ $b->kategorie?->label() ?? '— nicht zugeordnet —' }}</span></td>
                            <td><b>{{ $b->anzahl }}</b></td>
                            <td>{{ $b->offen }}</td>
                            <td>@if ($b->dringend > 0)<span class="badge amber">{{ $b->dringend }}</span>@else 0 @endif</td>
                            <td><span class="muted">{{ $b->letzteMeldung?->format('d.m.Y') ?? '—' }}</span></td>
                            <td>
                                @if ($b->kategorie === null)
                                    <span class="muted" title="Meldungen ohne Anlagenbezug — Betriebsmittel zuordnen">Anlage zuordnen</span>
                                @elseif ($b->hatVorsorge)
                                    <span class="ampel-dot" style="background:var(--green-700,#2f7d32)"></span>
                                    <span class="badge green">hinterlegt</span>
                                @else
                                    <span class="ampel-dot" style="background:var(--danger,#D2492F)"></span>
                                    <span class="badge red">fehlt</span>
                                @endif
                            </td>
                            @if ($darfVerwalten)
                                <td>
                                    @if ($b->kategorie !== null)
                                        @if ($b->hatVorsorge && $b->vorsorgeId)
                                            <button class="btn btn-ghost btn-sm" wire:click="bearbeiten({{ $b->vorsorgeId }})">bearbeiten</button>
                                        @else
                                            <button class="btn btn-primary btn-sm" wire:click="neuFuer({{ $b->assetId ?? 'null' }}, @js($b->bezeichnung), @js($b->kategorie?->value))">Vorsorge anlegen</button>
                                        @endif
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="muted" style="margin-top:6px;font-size:.85em">
                Zeigt die Top {{ $top->count() }} von insgesamt {{ $quellenGesamt }} Störquellen im Zeitraum.
            </p>
        @endif
    </div>

    {{-- Vorsorge-Formular --}}
    @if ($darfVerwalten)
        <div class="card">
            <div class="card-head">
                <h3>{{ $editId ? 'Vorsorge bearbeiten' : 'Notfallvorsorge' }}</h3>
                @unless ($formOffen)
                    <button class="btn btn-primary btn-sm" wire:click="neu">+ Neue Vorsorge</button>
                @endunless
            </div>

            @if ($formOffen)
                <form wire:submit="speichern">
                    <div class="form-row">
                        <div class="field">
                            <label>Störquelle / Bezeichnung *</label>
                            <input type="text" wire:model="v_bezeichnung" placeholder="z. B. Aufzug Haupthaus, Rufanlage WB 1" />
                            @error('v_bezeichnung')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Kategorie *</label>
                            <select wire:model="v_kategorie">
                                @foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label>Konkretes Betriebsmittel (optional — sonst gilt die Vorsorge kategorieweit)</label>
                        <select wire:model="v_asset">
                            <option value="">– kategorieweit –</option>
                            @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->bezeichnung }}</option>@endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Mindest-Ersatzteile (Vorhalten)</label>
                        <textarea wire:model="v_ersatzteile" rows="3" placeholder="z. B. 2× Türkontakt, 1× Notruf-Taster, Sicherungen 16 A …"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label>Dienstleister</label>
                            <input type="text" wire:model="v_dienstleister" placeholder="z. B. Aufzugswartung Müller GmbH" />
                        </div>
                        <div class="field">
                            <label>Kontakt (Notruf)</label>
                            <input type="text" wire:model="v_kontakt" placeholder="z. B. 0800 1234567 / notdienst@…" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label>Reaktionszeit (schriftlich fixiert)</label>
                            <input type="text" wire:model="v_reaktionszeit" placeholder="z. B. 4 h, nächster Werktag, 24/7-Notdienst" />
                        </div>
                        <div class="field">
                            <label>davon in Stunden (optional, für Ampel)</label>
                            <input type="number" min="0" max="8760" wire:model="v_reaktionszeit_stunden" placeholder="z. B. 4" />
                            @error('v_reaktionszeit_stunden')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label>Interne Sofortmaßnahmen (Checkliste)</label>
                        @foreach ($v_sofort as $i => $schritt)
                            <div style="display:flex;gap:6px;margin-bottom:4px">
                                <input type="text" wire:model="v_sofort.{{ $i }}" placeholder="z. B. Aufzug abschalten + absperren, betroffene Bewohner umverlegen" />
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="schrittEntfernen({{ $i }})" title="entfernen">✕</button>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="schrittHinzufuegen">+ Schritt</button>
                    </div>

                    <div class="field">
                        <label>Notiz</label>
                        <textarea wire:model="v_notiz" rows="2"></textarea>
                    </div>

                    <div style="display:flex;gap:8px">
                        <button class="btn btn-primary btn-sm" type="submit">Speichern</button>
                        <button class="btn btn-ghost btn-sm" type="button" wire:click="abbrechen">Abbrechen</button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Hinterlegte Vorsorge-Profile --}}
        <div class="card">
            <div class="card-head">
                <h3>Hinterlegte Vorsorge</h3>
                <span class="badge {{ $vorsorgen->isEmpty() ? 'gray' : 'green' }}">{{ $vorsorgen->count() }}</span>
            </div>
            @forelse ($vorsorgen as $v)
                <div class="qm-item">
                    <div class="qm-anf">
                        <b>{{ $v->bezeichnung }}</b>
                        <span class="muted">· {{ $v->kategorie->label() }}</span>
                        @if ($v->asset)<span class="muted">· {{ $v->asset->bezeichnung }}</span>@endif
                        @if ($v->reaktionszeit)<span class="badge amber" style="margin-left:auto">Reaktion: {{ $v->reaktionszeit }}</span>@endif
                    </div>
                    @if ($v->mindest_ersatzteile)
                        <p style="margin:4px 0"><b>Ersatzteile:</b> <span class="muted">{{ $v->mindest_ersatzteile }}</span></p>
                    @endif
                    @if ($v->dienstleister)
                        <p style="margin:4px 0"><b>Dienstleister:</b> <span class="muted">{{ $v->dienstleister }}@if ($v->dienstleister_kontakt) · {{ $v->dienstleister_kontakt }}@endif</span></p>
                    @endif
                    @if (count($v->sofortmassnahmenListe()) > 0)
                        <p style="margin:4px 0 2px"><b>Sofortmaßnahmen:</b></p>
                        <ul style="margin:0 0 4px 18px">
                            @foreach ($v->sofortmassnahmenListe() as $s)<li class="muted">{{ $s }}</li>@endforeach
                        </ul>
                    @endif
                    <div style="display:flex;gap:8px;margin-top:6px">
                        <button class="btn btn-ghost btn-sm" wire:click="bearbeiten({{ $v->id }})">bearbeiten</button>
                        <button class="btn btn-danger btn-sm" wire:click="loeschen({{ $v->id }})" wire:confirm="Vorsorge wirklich entfernen?">entfernen</button>
                    </div>
                </div>
            @empty
                <p class="empty">Noch keine Vorsorge hinterlegt. Lege oben für die häufigsten Störquellen eine an.</p>
            @endforelse
        </div>
    @endif
</div>
