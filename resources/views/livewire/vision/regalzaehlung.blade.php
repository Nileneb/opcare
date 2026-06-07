<div>
    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Lager &amp; Vision</p>
            <h1>Regalzählung (YOLO-Bestandserkennung)</h1>
            <p class="lead">Regalfoto hochladen → YOLO-Modell zählt Objekte → Mengen prüfen und korrigieren → in offene Inventur buchen.</p>
        </div>
    </div>

    {{-- DSGVO-Hinweis --}}
    <div class="card" style="border-left:4px solid var(--color-amber,#f59e0b);padding:12px 16px;margin-bottom:16px">
        <strong>Datenschutz-Hinweis:</strong> Bitte ausschließlich Regalfotos hochladen — <strong>keine Bewohner, keine Mitarbeiter</strong> im Bild. Personenbezogene Bilder werden nicht verarbeitet und dürfen nicht hochgeladen werden (Art. 9 DSGVO).
    </div>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    {{-- Modell-Status --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-head"><h3>Aktives Modell</h3></div>
        @if ($aktivesModell)
            <p><strong>{{ $aktivesModell->model_path }}</strong> (Version {{ $aktivesModell->version }})</p>
            @if ($aktivesModell->class_names)
                <p style="font-size:.875rem;color:#6b7280">Klassen: {{ implode(', ', $aktivesModell->class_names) }}</p>
            @endif
        @else
            <p style="color:#f59e0b">Kein trainiertes Modell vorhanden — Basis-Erkennung (<code>{{ config('vision.default_model', '/models/base/yolo11n.pt') }}</code>) wird verwendet.</p>
        @endif
    </div>

    {{-- Karte 1: Zählen --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-head"><h3>1 · Regal fotografieren &amp; zählen</h3></div>

        @if ($keinModellHinweis)
            <div style="background:#fef3c7;border-left:3px solid #f59e0b;padding:8px 12px;margin-bottom:12px;border-radius:4px">
                {{ $keinModellHinweis }}
            </div>
        @endif

        <form wire:submit="zaehlen">
            <div class="field" style="max-width:480px">
                <label>Regalfoto</label>
                <input type="file" wire:model="foto" accept="image/*" />
                @error('foto') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="zaehlen">Zählen starten</span>
                <span wire:loading wire:target="zaehlen">Erkenne…</span>
            </button>
        </form>
    </div>

    {{-- Karte 2: Ergebnis & Buchen --}}
    @foreach ($aufnahmen as $aufnahme)
        @if ($aufnahme->detektionen->isNotEmpty())
        <div class="card" style="margin-bottom:16px">
            <div class="card-head">
                <h3>2 · Erkennungen aus Aufnahme #{{ $aufnahme->id }} — {{ $aufnahme->created_at->format('d.m.Y H:i') }}</h3>
            </div>

            {{-- Inventur-Auswahl --}}
            <div class="field" style="max-width:360px;margin-bottom:12px">
                <label>Ziel-Inventur (offen)</label>
                <select wire:model="inventurId">
                    <option value="">— bitte wählen —</option>
                    @foreach ($offeneInventuren as $inv)
                        <option value="{{ $inv->id }}">
                            Inventur #{{ $inv->id }} — {{ $inv->stichtag->format('d.m.Y') }}
                            @if ($inv->abteilung) ({{ $inv->abteilung->value }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('inventurId') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead>
                    <tr style="border-bottom:2px solid #e5e7eb;text-align:left">
                        <th style="padding:6px 8px">Label</th>
                        <th style="padding:6px 8px">Artikel</th>
                        <th style="padding:6px 8px">Erkannte Anzahl</th>
                        <th style="padding:6px 8px">Menge (editierbar)</th>
                        <th style="padding:6px 8px">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($aufnahme->detektionen as $det)
                        <tr style="border-bottom:1px solid #f3f4f6">
                            <td style="padding:6px 8px"><code>{{ $det->label }}</code></td>
                            <td style="padding:6px 8px">
                                @if ($det->artikel)
                                    {{ $det->artikel->name }} <small style="color:#6b7280">({{ $det->artikel->einheit }})</small>
                                @else
                                    <span style="color:#9ca3af">nicht gemappt</span>
                                @endif
                            </td>
                            <td style="padding:6px 8px">{{ number_format((float)$det->menge_vorschlag, 2) }}</td>
                            <td style="padding:6px 8px">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="ist.{{ $det->id }}"
                                    style="width:100px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px"
                                />
                                @error("ist.{$det->id}") <span class="field-error" style="display:block">{{ $message }}</span> @enderror
                            </td>
                            <td style="padding:6px 8px">
                                @if ($det->artikel_id)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        wire:click="buchen({{ $det->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="buchen({{ $det->id }})">
                                        In Inventur buchen
                                    </button>
                                @else
                                    <span style="color:#9ca3af;font-size:.8rem">kein Artikel</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @endforeach

    @if ($aufnahmen->isEmpty() || $aufnahmen->every(fn($a) => $a->detektionen->isEmpty()))
        <div class="card" style="margin-bottom:16px;color:#6b7280;padding:24px;text-align:center">
            Noch keine Erkennungen — Regalfoto hochladen und Zählen starten.
        </div>
    @endif

    {{-- Karte 3: Labeling / Auto-Annotation --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-head"><h3>3 · Labeling (Auto-Annotation für Training-Dataset)</h3></div>
        <p style="font-size:.875rem;color:#6b7280;margin-bottom:12px">Foto wird automatisch annotiert — die Box-Vorschläge werden gespeichert und können später zum Modelltraining verwendet werden.</p>

        <form wire:submit="annotieren">
            <div class="field" style="max-width:480px">
                <label>Regalfoto für Labeling</label>
                <input type="file" wire:model="foto" accept="image/*" />
                @error('foto') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="annotieren">Automatisch annotieren</span>
                <span wire:loading wire:target="annotieren">Annotiere…</span>
            </button>
        </form>

        @if ($annotierVorschlaege)
            <div style="margin-top:12px">
                <strong>{{ count($annotierVorschlaege) }} Vorschlag/Vorschläge:</strong>
                <ul style="font-size:.85rem;margin-top:6px">
                    @foreach ($annotierVorschlaege as $v)
                        <li>Label: <code>{{ $v['label'] ?? '?' }}</code> — Konfidenz: {{ number_format($v['confidence'] ?? 0, 2) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Karte 4: Training --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-head"><h3>4 · Modell-Training</h3></div>

        @if ($trainingAktiv)
            <p style="font-size:.875rem;color:#6b7280;margin-bottom:12px">Training mit den gesammelten Labeling-Aufnahmen dieses Tenants starten.</p>
            <div style="background:#fff7ed;border-left:3px solid #f59e0b;padding:10px 14px;border-radius:4px;color:#92400e;margin-bottom:12px">
                <strong>Dataset-Pipeline folgt:</strong> Das Modelltraining setzt einen ZIP-Export der gelabelten Aufnahmen voraus — dieser Baustein ist als Folge-Inkrement geplant.
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" class="btn btn-primary" wire:click="trainingStarten" wire:loading.attr="disabled" disabled title="Dataset-/ZIP-Pipeline noch nicht implementiert">
                    Training starten (Datenpipeline folgt)
                </button>
                @if ($trainingJobId)
                    <button type="button" class="btn btn-secondary" wire:click="trainingStatusAktualisieren">Status aktualisieren</button>
                @endif
            </div>

            @error('training')
                <div style="margin-top:10px;padding:10px;background:#fef2f2;border-left:3px solid #f87171;border-radius:4px;color:#991b1b">
                    {{ $message }}
                </div>
            @enderror

            @if ($trainingJobId)
                <div style="margin-top:12px;padding:10px;background:#f9fafb;border-radius:6px">
                    <strong>Job-ID:</strong> <code>{{ $trainingJobId }}</code>
                    @if ($trainingStatus)
                        <br><strong>Status:</strong> {{ $trainingStatus['status'] ?? '?' }}
                        @if (!empty($trainingStatus['model_path']))
                            <br><strong>Modell-Pfad:</strong> <code>{{ $trainingStatus['model_path'] }}</code>
                        @endif
                        @if (!empty($trainingStatus['class_names']))
                            <br><strong>Klassen:</strong> {{ implode(', ', $trainingStatus['class_names']) }}
                        @endif
                    @endif
                </div>
            @endif
        @else
            <div style="background:#f3f4f6;border-left:3px solid #9ca3af;padding:10px 14px;border-radius:4px;color:#6b7280">
                <strong>Training stillgelegt (Inbetriebnahme-Schalter):</strong>
                Das Modelltraining ist bis zur offiziellen Zulassung deaktiviert.
                Aktivieren: <code>VISION_TRAINING_AKTIV=true</code> setzen (siehe <code>docs/INBETRIEBNAHME.md §5</code>).
            </div>
        @endif
    </div>
</div>
