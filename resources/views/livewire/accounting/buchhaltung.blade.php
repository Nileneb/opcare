<div>
    <div class="page-head">
        <div><p class="kicker">Verwaltung · Finanzen</p><h1>Buchhaltung & Warenwirtschaft</h1>
            <p class="lead">Doppelte Buchführung (Soll/Haben) je Konto, verknüpft mit der Lagerwirtschaft der Abteilungen.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Kontensalden</h3><span class="badge gray">Stand jetzt</span></div>
        @foreach ($kontoTypen as $typ)
            @php($gruppe = $kontenNachTyp[$typ->value] ?? collect())
            @if ($gruppe->isNotEmpty())
                <p class="kicker" style="margin:10px 0 4px">{{ $typ->label() }}</p>
                <table class="data-table">
                    <thead><tr><th style="width:90px">Konto</th><th>Bezeichnung</th><th style="width:160px;text-align:right">Saldo</th></tr></thead>
                    <tbody>
                        @foreach ($gruppe as $k)
                            <tr>
                                <td><code>{{ $k->nummer }}</code></td>
                                <td>{{ $k->name }}</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums"><b>{{ number_format($k->saldo(), 2, ',', '.') }} €</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    </div>

    <div class="card">
        <div class="card-head"><h3>Lagerartikel</h3><span class="badge gray">{{ $artikel->count() }} Artikel</span></div>
        <table class="data-table">
            <thead><tr><th>Artikel</th><th>Abteilung</th><th style="text-align:right">Bestand</th><th style="text-align:right">EK-Preis</th><th></th></tr></thead>
            <tbody>
                @forelse ($artikel as $a)
                    <tr @class(['row-warn' => $a->unterbestand()])>
                        <td><b>{{ $a->name }}</b> <span class="muted">/ {{ $a->einheit }}</span></td>
                        <td>{{ $a->abteilung->label() }}</td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums">
                            {{ number_format((float) $a->bestand, 2, ',', '.') }} {{ $a->einheit }}
                            @if ($a->unterbestand())<span class="badge red" title="unter Mindestbestand {{ $a->mindestbestand }}">⚠ Unterbestand</span>@endif
                        </td>
                        <td style="text-align:right">{{ $a->einkaufspreis !== null ? number_format((float) $a->einkaufspreis, 2, ',', '.').' €' : '–' }}</td>
                        <td style="text-align:right"><button class="btn btn-ghost btn-sm" wire:click="$set('beweg_artikel', {{ $a->id }})">buchen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><p class="empty">Noch keine Artikel angelegt.</p></td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="form-row-2" style="margin-top:14px;gap:18px">
            <form wire:submit="wareneingang" style="border:1px solid var(--line-cool);border-radius:8px;padding:12px">
                <p class="kicker">Wareneingang (Soll Warenbestand · Haben Verbindlichkeiten)</p>
                <div class="field"><label>Artikel</label>
                    <select wire:model="beweg_artikel"><option value="">– wählen –</option>@foreach ($artikel as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->abteilung->label() }})</option>@endforeach</select>
                    @error('beweg_artikel')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="form-row-2">
                    <div class="field"><label>Menge</label><input type="number" step="0.01" wire:model="beweg_menge" />@error('beweg_menge')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>EK-Preis (optional)</label><input type="number" step="0.01" wire:model="beweg_preis" placeholder="je Einheit" /></div>
                </div>
                <div class="field"><label>Notiz</label><input type="text" wire:model="beweg_notiz" placeholder="z. B. Lieferant" /></div>
                <button class="btn btn-primary btn-sm">+ Eingang buchen</button>
            </form>

            <form wire:submit="verbrauch" style="border:1px solid var(--line-cool);border-radius:8px;padding:12px">
                <p class="kicker">Verbrauch (Soll Abteilungs-Aufwand · Haben Warenbestand)</p>
                <div class="field"><label>Artikel</label>
                    <select wire:model="beweg_artikel"><option value="">– wählen –</option>@foreach ($artikel as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->abteilung->label() }})</option>@endforeach</select>
                    @error('beweg_artikel')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Menge</label><input type="number" step="0.01" wire:model="beweg_menge" />@error('beweg_menge')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Notiz</label><input type="text" wire:model="beweg_notiz" placeholder="z. B. Wohnbereich 2" /></div>
                <button class="btn btn-ghost btn-sm">– Verbrauch buchen</button>
            </form>
        </div>

        <form wire:submit="artikelAnlegen" style="margin-top:14px;border-top:1px solid var(--line-cool);padding-top:14px">
            <p class="kicker">Neuen Artikel anlegen</p>
            <div class="form-row-3">
                <div class="field"><label>Name</label><input type="text" wire:model="a_name" />@error('a_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Einheit</label><input type="text" wire:model="a_einheit" /></div>
                <div class="field"><label>Abteilung</label><select wire:model="a_abteilung">@foreach ($abteilungen as $ab)<option value="{{ $ab->value }}">{{ $ab->label() }}</option>@endforeach</select></div>
            </div>
            <div class="form-row-2">
                <div class="field"><label>Mindestbestand (optional)</label><input type="number" step="0.01" wire:model="a_mindestbestand" /></div>
                <div class="field"><label>EK-Preis (optional)</label><input type="number" step="0.01" wire:model="a_einkaufspreis" /></div>
            </div>
            <button class="btn btn-ghost btn-sm">+ Artikel</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Freie Buchung</h3><span class="badge gray">Hauptbuch</span></div>
        <p class="muted">Generischer Buchungssatz „Soll an Haben" (z. B. Einzahlung, Zahlung, Korrektur) direkt im Hauptbuch (GoB / PBV).</p>
        <form wire:submit="freieBuchung" style="margin-top:10px">
            <div class="form-row-2">
                <div class="field"><label>Soll-Konto</label>
                    <select wire:model="b_soll"><option value="">– wählen –</option>@foreach ($konten as $k)<option value="{{ $k->id }}">{{ $k->nummer }} · {{ $k->name }}</option>@endforeach</select>
                    @error('b_soll')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Haben-Konto</label>
                    <select wire:model="b_haben"><option value="">– wählen –</option>@foreach ($konten as $k)<option value="{{ $k->id }}">{{ $k->nummer }} · {{ $k->name }}</option>@endforeach</select>
                    @error('b_haben')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Betrag (€)</label><input type="number" step="0.01" wire:model="b_betrag" />@error('b_betrag')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Datum</label><input type="date" wire:model="b_datum" />@error('b_datum')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Beleg (optional)</label><input type="text" wire:model="b_beleg" placeholder="Beleg-Nr." /></div>
            </div>
            <div class="field"><label>Buchungstext</label><input type="text" wire:model="b_text" placeholder="z. B. Bareinzahlung Spende" />@error('b_text')<span class="err">{{ $message }}</span>@enderror</div>
            <button class="btn btn-primary btn-sm">Buchen</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Journal</h3><span class="badge gray">letzte {{ $buchungen->count() }} Buchungen</span></div>
        <table class="data-table">
            <thead><tr><th style="width:100px">Datum</th><th>Soll</th><th>Haben</th><th>Text</th><th style="text-align:right">Betrag</th></tr></thead>
            <tbody>
                @forelse ($buchungen as $b)
                    <tr>
                        <td>{{ $b->datum->format('d.m.Y') }}</td>
                        <td><code>{{ $b->sollKonto->nummer }}</code> {{ $b->sollKonto->name }}</td>
                        <td><code>{{ $b->habenKonto->nummer }}</code> {{ $b->habenKonto->name }}</td>
                        <td>{{ $b->text }}</td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format((float) $b->betrag, 2, ',', '.') }} €</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><p class="empty">Noch keine Buchungen.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
