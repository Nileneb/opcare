<div>
    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Finanzen</p>
            <h1>Beschaffung &amp; Bestellwesen</h1>
            <p class="lead">Bestellungen beim Lieferanten anlegen, Wareneingänge gegen offene Positionen buchen.</p>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Neue Bestellung --}}
    <div class="card">
        <div class="card-head"><h3>Neue Bestellung</h3>
            @if ($bedarfe->isNotEmpty())
                <button class="btn btn-ghost btn-sm" wire:click="bedarfUebernehmen">Bedarf übernehmen ({{ $bedarfe->count() }})</button>
            @endif
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div>
                <label class="form-label">Lieferant *</label>
                <select class="form-control" wire:model="b_lieferant">
                    <option value="">— bitte wählen —</option>
                    @foreach ($lieferanten as $l)
                        <option value="{{ $l->id }}">{{ $l->name }}</option>
                    @endforeach
                </select>
                @error('b_lieferant')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div>
                <label class="form-label">Bestelldatum *</label>
                <input type="date" class="form-control" wire:model="b_datum" />
                @error('b_datum')<span class="form-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div style="margin-bottom:12px">
            <label class="form-label">Notiz</label>
            <input type="text" class="form-control" wire:model="b_notiz" maxlength="500" placeholder="optional" />
        </div>

        <table class="data-table" style="margin-bottom:8px">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th style="width:120px">Menge</th>
                    <th style="width:120px">EK-Preis</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($b_positionen as $i => $pos)
                    <tr>
                        <td>
                            <select class="form-control" wire:model="b_positionen.{{ $i }}.artikel_id">
                                <option value="">— Artikel —</option>
                                @foreach ($artikel as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->einheit }})</option>
                                @endforeach
                            </select>
                            @error("b_positionen.{$i}.artikel_id")<span class="form-error">{{ $message }}</span>@enderror
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0.01" class="form-control" wire:model="b_positionen.{{ $i }}.menge" />
                            @error("b_positionen.{$i}.menge")<span class="form-error">{{ $message }}</span>@enderror
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control" wire:model="b_positionen.{{ $i }}.preis" placeholder="–" />
                        </td>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="positionEntfernen({{ $i }})">✕</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="display:flex;gap:8px">
            <button class="btn btn-ghost btn-sm" wire:click="positionHinzufuegen">+ Position</button>
            <button class="btn btn-primary" wire:click="bestellungAnlegen">Bestellung anlegen</button>
        </div>
    </div>

    {{-- Wareneingang gegen offene Position --}}
    @if ($offenePositionen->isNotEmpty())
    <div class="card">
        <div class="card-head"><h3>Wareneingang buchen</h3><span class="badge amber">{{ $offenePositionen->count() }} offene Position(en)</span></div>

        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px;margin-bottom:12px">
            <div>
                <label class="form-label">Offene Position *</label>
                <select class="form-control" wire:model="lief_pos_id">
                    <option value="">— bitte wählen —</option>
                    @foreach ($offenePositionen as $p)
                        <option value="{{ $p->id }}">
                            #{{ $p->bestellung_id }} · {{ $p->artikel->name }} · offen: {{ number_format($p->restMenge(), 2, ',', '.') }} {{ $p->artikel->einheit }}
                        </option>
                    @endforeach
                </select>
                @error('lief_pos_id')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div>
                <label class="form-label">Menge *</label>
                <input type="number" step="0.01" min="0.01" class="form-control" wire:model="lief_menge" />
                @error('lief_menge')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div>
                <label class="form-label">Charge</label>
                <input type="text" class="form-control" wire:model="lief_charge" maxlength="120" placeholder="optional" />
            </div>
            <div>
                <label class="form-label">MHD</label>
                <input type="date" class="form-control" wire:model="lief_mhd" />
            </div>
        </div>
        <button class="btn btn-primary" wire:click="positionLiefern">Wareneingang buchen</button>
    </div>
    @endif

    {{-- Bestellübersicht --}}
    <div class="card">
        <div class="card-head"><h3>Bestellungen</h3><span class="badge gray">letzte 50</span></div>
        @forelse ($bestellungen as $b)
            <div style="border:1px solid var(--border,#e5e7eb);border-radius:6px;padding:12px;margin-bottom:8px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <b>Bestellung #{{ $b->id }}</b>
                    <span class="muted">{{ $b->lieferant->name }}</span>
                    <span class="muted">{{ $b->bestelldatum->format('d.m.Y') }}</span>
                    @php
                        $badgeColor = match($b->status) {
                            \App\Domains\Accounting\Enums\BestellStatus::Geliefert => 'green',
                            \App\Domains\Accounting\Enums\BestellStatus::TeilweiseGeliefert => 'amber',
                            \App\Domains\Accounting\Enums\BestellStatus::Storniert => 'red',
                            default => 'gray',
                        };
                    @endphp
                    <span class="badge {{ $badgeColor }}">{{ $b->status->label() }}</span>
                </div>
                <table class="data-table" style="font-size:0.875rem">
                    <thead>
                        <tr>
                            <th>Artikel</th>
                            <th style="text-align:right;width:120px">Bestellt</th>
                            <th style="text-align:right;width:120px">Geliefert</th>
                            <th style="width:80px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($b->positionen as $p)
                            <tr>
                                <td>{{ $p->artikel->name }} <span class="muted">/{{ $p->artikel->einheit }}</span></td>
                                <td style="text-align:right">{{ number_format((float)$p->menge_bestellt, 2, ',', '.') }}</td>
                                <td style="text-align:right">{{ number_format((float)$p->menge_geliefert, 2, ',', '.') }}</td>
                                <td>
                                    @if ($p->offen())
                                        <span class="badge amber">offen</span>
                                    @else
                                        <span class="badge green">fertig</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($b->notiz)
                    <p class="muted" style="margin:6px 0 0;font-size:0.85rem">{{ $b->notiz }}</p>
                @endif
            </div>
        @empty
            <p class="empty">Noch keine Bestellungen vorhanden.</p>
        @endforelse
    </div>
</div>
