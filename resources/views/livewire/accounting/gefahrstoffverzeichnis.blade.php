<div class="page">
    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Finanzen · Arbeitsschutz</p>
            <h1>Gefahrstoffverzeichnis</h1>
            <p class="lead">Gesetzlich gefordertes Verzeichnis aller Gefahrstoffe im Betrieb (§ 6 Abs. 12 GefStoffV, Fassung 17.12.2025) mit Pflichtangaben nach Nr. 1–5 und Zusatzangaben nach TRGS 510/555.</p>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Verzeichnis-Tabelle --}}
    <div class="card">
        <div class="card-head">
            <h3>Verzeichnis der Gefahrstoffe</h3>
            <span class="badge gray">§ 6 Abs. 12 GefStoffV</span>
        </div>
        @if ($eintraege->isEmpty())
            <p class="empty">Keine Gefahrstoffe erfasst. Tragen Sie einen Artikel unten ein.</p>
        @else
            <table class="data-table" style="font-size:.85rem">
                <thead>
                    <tr>
                        <th>Bezeichnung <small class="muted">(Nr. 1)</small></th>
                        <th>Signalwort / GHS</th>
                        <th>H-Sätze <small class="muted">(Nr. 2)</small></th>
                        <th>Mengenbereich <small class="muted">(Nr. 3)</small></th>
                        <th>Arbeitsbereiche <small class="muted">(Nr. 4)</small></th>
                        <th>Lagerort</th>
                        <th>SDB / Version <small class="muted">(Nr. 5)</small></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($eintraege as $artikel)
                        @php($gs = $artikel->gefahrstoffDaten)
                        <tr>
                            <td><b>{{ $artikel->name }}</b><br><span class="muted">{{ $artikel->einheit }}</span></td>
                            <td>
                                @if ($gs)
                                    @if ($gs->signalwort)
                                        <span class="badge {{ $gs->signalwort === 'Gefahr' ? 'red' : 'amber' }}">{{ $gs->signalwort }}</span>
                                    @endif
                                    @if ($gs->ghs_piktogramme)
                                        <div style="margin-top:2px;font-size:.75rem">{{ implode(' ', $gs->ghs_piktogramme) }}</div>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($gs?->h_saetze)
                                    {{ implode(', ', $gs->h_saetze) }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $gs?->mengenbereich ?? '—' }}</td>
                            <td style="max-width:180px;white-space:pre-wrap">{{ $gs?->arbeitsbereiche ?? '—' }}</td>
                            <td>{{ $gs?->lagerort ?? '—' }}</td>
                            <td>
                                @if ($gs)
                                    @php($sdbMedia = $gs->getFirstMedia('sdb'))
                                    @if ($sdbMedia)
                                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $sdbMedia->id]) }}" class="btn btn-ghost btn-sm">SDB ↓</a>
                                    @endif
                                    @if ($gs->sdb_version_datum)
                                        <span class="muted" style="font-size:.75rem;display:block">v {{ $gs->sdb_version_datum->format('d.m.Y') }}</span>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td><button class="btn btn-ghost btn-sm" wire:click="editEintrag({{ $artikel->id }})">Bearbeiten</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Anlege- / Bearbeiten-Formular --}}
    <div class="card">
        <div class="card-head">
            <h3>Eintrag anlegen / bearbeiten</h3>
        </div>

        <div class="form-row-2" style="gap:12px">
            <div class="field">
                <label>Artikel <span class="required">*</span></label>
                <select wire:model="artikelId">
                    <option value="">— Artikel wählen —</option>
                    @foreach ($alleArtikel as $a)
                        <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->einheit }}){{ $a->gefahrstoff ? ' ✓' : '' }}</option>
                    @endforeach
                </select>
                @error('artikelId')<span class="err">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label>Signalwort (CLP-VO)</label>
                <select wire:model="signalwort">
                    <option value="">— keines —</option>
                    <option value="Gefahr">Gefahr</option>
                    <option value="Achtung">Achtung</option>
                </select>
            </div>
        </div>

        <div class="field" style="margin-top:10px">
            <label>GHS-Piktogramme (TRGS 510) — Mehrfachauswahl</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
                @foreach ($piktogramme as $p)
                    <label style="display:flex;align-items:center;gap:4px;font-size:.85rem;cursor:pointer">
                        <input type="checkbox" wire:model="ghsPiktogramme" value="{{ $p->value }}" />
                        {{ $p->label() }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="form-row-2" style="margin-top:10px;gap:12px">
            <div class="field">
                <label>H-Sätze, kommagetrennt <small class="muted">(§ 6 Abs. 12 Nr. 2)</small></label>
                <input type="text" wire:model="hSaetzeInput" placeholder="z. B. H226, H319" />
            </div>
            <div class="field">
                <label>P-Sätze, kommagetrennt (TRGS 555)</label>
                <input type="text" wire:model="pSaetzeInput" placeholder="z. B. P210, P260" />
            </div>
        </div>

        <div class="form-row-2" style="margin-top:10px;gap:12px">
            <div class="field">
                <label>Mengenbereich im Betrieb <small class="muted">(§ 6 Abs. 12 Nr. 3)</small></label>
                <input type="text" wire:model="mengenbereich" placeholder="z. B. < 1 Liter" />
            </div>
            <div class="field">
                <label>Lagerort (TRGS 510)</label>
                <input type="text" wire:model="lagerort" placeholder="z. B. Putzraum EG" />
            </div>
        </div>

        <div class="field" style="margin-top:10px">
            <label>Arbeitsbereiche mit möglicher Exposition <small class="muted">(§ 6 Abs. 12 Nr. 4)</small></label>
            <textarea wire:model="arbeitsbereiche" rows="2" placeholder="z. B. Küche, Reinigungsdienst"></textarea>
        </div>

        <div class="field" style="margin-top:10px">
            <label>Betriebsanweisung (§ 14 GefStoffV / TRGS 555)</label>
            <textarea wire:model="betriebsanweisung" rows="2" placeholder="Verweis auf BA oder Kurztext"></textarea>
        </div>

        <div class="form-row-2" style="margin-top:10px;gap:12px">
            <div class="field">
                <label>SDB-Versionsdatum (Art. 31 REACH) <small class="muted">(§ 6 Abs. 12 Nr. 5)</small></label>
                <input type="date" wire:model="sdbVersionDatum" />
            </div>
            <div class="field">
                <label>SDB-Datei (PDF)</label>
                <input type="file" wire:model="sdbFile" accept=".pdf" />
                @error('sdbFile')<span class="err">{{ $message }}</span>@enderror
            </div>
        </div>

        <div style="margin-top:14px;text-align:right">
            <button class="btn btn-primary btn-sm" wire:click="eintragSpeichern">Speichern</button>
        </div>
    </div>
</div>
