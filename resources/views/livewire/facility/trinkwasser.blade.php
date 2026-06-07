<div>
    <div class="page-head">
        <div>
            <p class="kicker">Haustechnik · Trinkwasser</p>
            <h1>Trinkwasser-Überwachung</h1>
            <p class="lead">Legionellen-Untersuchungspflicht (§ 31 TrinkwV 2023): Anlagen-Register, Probenahmestellen, Befund-Historie und § 51-Überschreitungs-Workflow.</p>
        </div>
        @if ($ueberfaellig > 0)
            <span class="badge red" title="überfällige Untersuchungen">{{ $ueberfaellig }} Untersuchung(en) überfällig</span>
        @endif
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Anlage anlegen --}}
    <div class="card">
        <div class="card-head"><h3>Trinkwasseranlage anlegen</h3><span class="badge gray">§ 31 TrinkwV 2023</span></div>
        <form wire:submit="anlageSpeichern">
            <div class="form-row-3">
                <div class="field">
                    <label>Bezeichnung *</label>
                    <input type="text" wire:model="bezeichnung" placeholder="z. B. Zentrale Warmwasserbereitung Haus 1" />
                    @error('bezeichnung')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Gebäude</label>
                    <input type="text" wire:model="gebaeude" placeholder="z. B. Haus 1" />
                </div>
                <div class="field">
                    <label>Untersuchungsintervall (Monate) *</label>
                    <input type="number" wire:model="intervall" min="1" max="120" />
                    @error('intervall')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Anlage anlegen</button>
        </form>
    </div>

    {{-- Anlagen-Liste --}}
    @forelse ($anlagen as $anlage)
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>{{ $anlage->bezeichnung }}</h3>
                    @if ($anlage->gebaeude)<span class="muted">{{ $anlage->gebaeude }}</span>@endif
                </div>
                @php $status = $anlage->faelligkeitsStatus(); @endphp
                @if ($status === 'rot')
                    <span class="badge red">Untersuchung überfällig</span>
                @elseif ($status === 'gelb')
                    <span class="badge amber">Untersuchung fällig in &lt; 30 Tagen</span>
                @else
                    <span class="badge green">Untersuchung aktuell</span>
                @endif
            </div>

            <div class="grid-3" style="margin-bottom:1rem">
                <div>
                    <span class="muted">Letztes Untersuchungsdatum</span><br>
                    {{ $anlage->letzte_untersuchung_am?->format('d.m.Y') ?? '—' }}
                </div>
                <div>
                    <span class="muted">Nächste Fälligkeit</span><br>
                    {{ $anlage->naechsteFaelligkeit()?->format('d.m.Y') ?? 'nicht bestimmbar' }}
                </div>
                <div>
                    <span class="muted">Intervall</span><br>
                    {{ $anlage->untersuchungsintervall_monate }} Monate
                </div>
            </div>

            {{-- § 51-Pflicht-Kasten bei offener Überschreitung --}}
            @if ($anlage->offeneUeberschreitung())
                <div class="alert alert-danger" style="border:2px solid #c0392b;background:#fdf0ef;padding:1rem;border-radius:4px;margin-bottom:1rem">
                    <strong>⚠ Maßnahmenwert 100 KbE/100 ml überschritten — § 51 TrinkwV 2023</strong><br>
                    Ursachenuntersuchung und Maßnahmen sind unverzüglich einzuleiten. Die zuständige Behörde (Gesundheitsamt) ist nach § 51 TrinkwV 2023 anzuzeigen.
                    <form wire:submit="meldungSetzen({{ $anlage->befunde->where('ueberschreitung', true)->whereNull('gesundheitsamt_gemeldet_am')->first()?->id ?? 0 }})" style="margin-top:.75rem">
                        <div class="field">
                            <label>Eingeleitete Maßnahmen *</label>
                            <textarea wire:model="meldung_massnahme" rows="3" placeholder="Beschreibung der eingeleiteten Maßnahmen (z. B. Thermische Desinfektion, Chlorung, Nutzungseinschränkung)"></textarea>
                            @error('meldung_massnahme')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <button class="btn btn-danger btn-sm">Maßnahmen dokumentieren + Gesundheitsamt gemeldet (heute)</button>
                    </form>
                </div>
            @endif

            {{-- Probenahmestellen --}}
            <div style="margin-bottom:1rem">
                <h4>Probenahmestellen</h4>
                @if ($anlage->probenahmestellen->isEmpty())
                    <p class="muted">Noch keine Probenahmestellen erfasst.</p>
                @else
                    <table class="data">
                        <thead><tr><th>Bezeichnung</th><th>Ort</th></tr></thead>
                        <tbody>
                            @foreach ($anlage->probenahmestellen as $stelle)
                                <tr>
                                    <td>{{ $stelle->bezeichnung }}</td>
                                    <td>{{ $stelle->ort ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <details style="margin-top:.5rem">
                    <summary class="btn btn-sm" style="cursor:pointer;display:inline-block">+ Probenahmestelle anlegen</summary>
                    <form wire:submit="stelleSpeichern({{ $anlage->id }})" style="margin-top:.5rem">
                        <div class="form-row-2">
                            <div class="field">
                                <label>Bezeichnung *</label>
                                <input type="text" wire:model="stelle_bezeichnung" placeholder="z. B. Austritt Warmwassererwärmer" />
                                @error('stelle_bezeichnung')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label>Ort</label>
                                <input type="text" wire:model="stelle_ort" placeholder="z. B. Technikraum EG" />
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm">Probenahmestelle anlegen</button>
                    </form>
                </details>
            </div>

            {{-- Befund erfassen --}}
            <div style="margin-bottom:1rem">
                <h4>Befund erfassen</h4>
                <form wire:submit="befundErfassen({{ $anlage->id }})">
                    <div class="form-row-4">
                        <div class="field">
                            <label>Probenahmestelle</label>
                            <select wire:model="stelle_id">
                                <option value="">— ohne Zuordnung —</option>
                                @foreach ($anlage->probenahmestellen as $stelle)
                                    <option value="{{ $stelle->id }}">{{ $stelle->bezeichnung }}</option>
                                @endforeach
                            </select>
                            @error('stelle_id')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Untersuchungsdatum *</label>
                            <input type="date" wire:model="untersucht_am" />
                            @error('untersucht_am')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>KbE/100 ml *</label>
                            <input type="number" wire:model="kbe" min="0" placeholder="0" />
                            @error('kbe')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Labor</label>
                            <input type="text" wire:model="labor" placeholder="z. B. Hygienelab GmbH" />
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Befund erfassen</button>
                </form>
            </div>

            {{-- Befund-Historie --}}
            @if ($anlage->befunde->isNotEmpty())
                <div>
                    <h4>Befund-Historie</h4>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Probenahmestelle</th>
                                <th>KbE/100 ml</th>
                                <th>Bewertung</th>
                                <th>Labor</th>
                                <th>Maßnahme / Meldung</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($anlage->befunde as $befund)
                                <tr>
                                    <td>{{ $befund->untersucht_am->format('d.m.Y') }}</td>
                                    <td>{{ $befund->probenahmestelle?->bezeichnung ?? '—' }}</td>
                                    <td>
                                        <strong>{{ $befund->kbe_pro_100ml }}</strong>
                                    </td>
                                    <td>
                                        @if ($befund->ueberschreitung)
                                            <span class="badge red">Überschreitung ≥ 100 KbE</span>
                                        @else
                                            <span class="badge green">unauffällig</span>
                                        @endif
                                    </td>
                                    <td>{{ $befund->labor ?? '—' }}</td>
                                    <td>
                                        @if ($befund->ueberschreitung)
                                            @if ($befund->gesundheitsamt_gemeldet_am && $befund->massnahme)
                                                <span class="badge green">Gemeldet {{ $befund->gesundheitsamt_gemeldet_am->format('d.m.Y') }}</span><br>
                                                <span class="muted" style="font-size:.85em">{{ Str::limit($befund->massnahme, 80) }}</span>
                                            @else
                                                <span class="badge red">Maßnahmen ausstehend</span>
                                            @endif
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <div class="card"><p class="muted">Noch keine Trinkwasseranlagen erfasst. Bitte oben eine Anlage anlegen.</p></div>
    @endforelse

    <p class="muted" style="font-size:.8em;margin-top:1.5rem">
        Rechtsgrundlage: Trinkwasserverordnung (TrinkwV 2023) § 31 (Untersuchungspflicht Großanlagen),
        § 51 (Meldepflicht bei Überschreitung des technischen Maßnahmenwerts), Anlage 3 Teil II
        (Technischer Maßnahmenwert 100 KbE/100 ml).
    </p>
</div>
