<div>
    @php
        $statusBadge = fn ($s) => match ($s->value) {
            'vorgeschlagen' => 'amber',
            'bestaetigt' => 'green',
            default => 'gray',
        };
    @endphp

    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Finanzen</p>
            <h1>Beleg→Wareneingang (Lieferschein-Capture)</h1>
            <p class="lead">Lieferschein fotografieren → die KI liest Positionen aus → je Position Artikel bestätigen und Wareneingang buchen. Der Vorschlag ist nie bindend — gebucht wird erst nach deiner Bestätigung.</p>
        </div>
    </div>

    <div class="card" style="border-left:4px solid var(--color-amber,#f59e0b);padding:12px 16px;margin-bottom:16px">
        <strong>Datenschutz-Hinweis:</strong> Diese Seite verarbeitet ausschließlich Waren- und Lieferantendaten. Bitte keine Belege mit Bewohnerdaten oder personenbezogenen Gesundheitsdaten hochladen.
    </div>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h3>Lieferschein hochladen</h3></div>
        <form wire:submit="analysieren">
            <div class="field" style="max-width:480px">
                <label>Lieferschein-Foto</label>
                <input type="file" wire:model="foto" accept="image/*" />
                @error('foto')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div wire:loading wire:target="foto" class="muted" style="margin-top:6px">Lade Bild …</div>
            <div wire:loading wire:target="analysieren" class="muted" style="margin-top:6px">Analysiere Lieferschein …</div>
            <button class="btn btn-primary btn-sm" style="margin-top:10px"
                wire:loading.attr="disabled" wire:target="analysieren">Analysieren</button>
        </form>

        @if ($fotoUrl)
            <div style="margin-top:16px">
                <p class="kicker">Letzter Lieferschein</p>
                <img src="{{ $fotoUrl }}" alt="Lieferschein-Vorschau"
                    style="max-height:260px;border:1px solid var(--border,#e5e7eb);border-radius:4px" />
            </div>
        @endif
    </div>

    @forelse ($analysen as $analyse)
        <div class="card">
            <div class="card-head">
                <h3>
                    Lieferschein #{{ $analyse->id }}
                    @if ($analyse->lieferant_text)
                        <span class="muted">· {{ $analyse->lieferant_text }}</span>
                    @endif
                    @if ($analyse->datum)
                        <span class="muted">· {{ $analyse->datum->format('d.m.Y') }}</span>
                    @endif
                </h3>
                <span class="badge gray">{{ $analyse->positionen->count() }} Pos.</span>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Beleg-Text</th>
                        <th>Artikel</th>
                        <th>Menge / Einheit</th>
                        <th>Charge / MHD</th>
                        <th>Buchungsziel</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($analyse->positionen as $pos)
                        <tr>
                            <td>
                                {{ $pos->text }}
                                @if ($pos->konfidenz !== null)
                                    <br><span class="badge {{ $pos->konfidenz >= 0.8 ? 'green' : 'amber' }}">{{ number_format((float) $pos->konfidenz * 100, 0) }} %</span>
                                @endif
                            </td>
                            <td style="min-width:200px">
                                @if ($pos->offen())
                                    <select wire:model="ist.{{ $pos->id }}.artikel_id" style="width:100%">
                                        <option value="">– Artikel wählen –</option>
                                        @if ($pos->kandidaten)
                                            <optgroup label="Vorschläge">
                                                @foreach ($pos->kandidaten as $k)
                                                    <option value="{{ $k['artikel_id'] }}">
                                                        {{ $k['name'] }}
                                                        ({{ number_format((float) ($k['score'] ?? 0) * 100, 0) }} %
                                                        · {{ $k['quelle'] ?? '–' }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <optgroup label="Alle Artikel">
                                            @foreach ($artikel as $a)
                                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    @error("ist.{$pos->id}.artikel_id")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    {{ $pos->artikel?->name ?? '–' }}
                                @endif
                            </td>
                            <td>
                                @if ($pos->offen())
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <input type="number" step="0.01" wire:model="ist.{{ $pos->id }}.menge"
                                            style="width:80px" placeholder="Menge" />
                                        <span class="muted">{{ $pos->einheit }}</span>
                                    </div>
                                    @error("ist.{$pos->id}.menge")<span class="err">{{ $message }}</span>@enderror
                                    <div style="margin-top:4px">
                                        <input type="number" step="0.01" wire:model="ist.{{ $pos->id }}.preis"
                                            style="width:80px" placeholder="Preis €" />
                                        @error("ist.{$pos->id}.preis")<span class="err">{{ $message }}</span>@enderror
                                    </div>
                                @else
                                    {{ $pos->menge }} {{ $pos->einheit }}
                                    @if ($pos->einzelpreis)
                                        <br><span class="muted">{{ number_format((float) $pos->einzelpreis, 2, ',', '.') }} €</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if ($pos->offen())
                                    <input type="text" wire:model="ist.{{ $pos->id }}.charge"
                                        style="width:100px" placeholder="Charge" />
                                    @error("ist.{$pos->id}.charge")<span class="err">{{ $message }}</span>@enderror
                                    <br>
                                    <input type="date" wire:model="ist.{{ $pos->id }}.mhd"
                                        style="width:130px;margin-top:4px" />
                                    @error("ist.{$pos->id}.mhd")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    {{ $pos->charge_nr ?: '–' }}
                                    @if ($pos->mhd)
                                        <br><span class="muted">MHD: {{ $pos->mhd->format('d.m.Y') }}</span>
                                    @endif
                                @endif
                            </td>
                            <td style="min-width:180px">
                                @if ($pos->offen())
                                    <select wire:model="ist.{{ $pos->id }}.bestellposition_id" style="width:100%">
                                        <option value="">Standalone</option>
                                        @foreach ($bestellpositionen as $bp)
                                            <option value="{{ $bp->id }}">
                                                {{ $bp->artikel?->name }} ({{ $bp->menge_offen }} offen)
                                            </option>
                                        @endforeach
                                    </select>
                                    @error("ist.{$pos->id}.bestellposition_id")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    @if ($pos->wareneingang_bewegung_id)
                                        Bewegung #{{ $pos->wareneingang_bewegung_id }}
                                    @else
                                        –
                                    @endif
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $statusBadge($pos->status) }}">{{ $pos->status->label() }}</span>
                            </td>
                            <td style="white-space:nowrap">
                                @if ($pos->offen())
                                    <button class="btn btn-primary btn-sm"
                                        wire:click="bestaetige({{ $pos->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="bestaetige({{ $pos->id }})">Buchen</button>
                                    <button class="btn btn-ghost btn-sm"
                                        wire:click="verwerfe({{ $pos->id }})"
                                        wire:confirm="Position verwerfen?">Verwerfen</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty">Keine Positionen in dieser Analyse.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div class="card">
            <p class="empty" style="padding:24px">Noch kein Lieferschein erfasst. Foto hochladen und „Analysieren" klicken.</p>
        </div>
    @endforelse
</div>
