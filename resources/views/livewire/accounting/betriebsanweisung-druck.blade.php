<style>
@media print {
    .app-top, .app-nav, header, nav, .btn-drucken, .no-print { display: none !important; }
    body { margin: 0; padding: 0; }
    .ba-seite { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: 100% !important; }
    .ba-seite { page-break-inside: avoid; }
}
</style>

<div class="page">
    <div class="page-head no-print">
        <div>
            <p class="kicker">Verwaltung · Finanzen · Arbeitsschutz</p>
            <h1>Betriebsanweisung</h1>
            <p class="lead">§ 14 Abs. 1 GefStoffV · TRGS 555 — Druckansicht</p>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-start">
            <a href="{{ route('gefahrstoffe') }}" class="btn btn-ghost btn-sm">← Zurück</a>
            <button class="btn btn-primary btn-sm btn-drucken" onclick="window.print()">Drucken</button>
        </div>
    </div>

    {{-- Betriebsanweisung-Dokument --}}
    <div class="ba-seite card" style="max-width:800px;margin:0 auto;padding:24px 32px">

        {{-- Kopfzeile --}}
        <div style="border:2px solid #1a1a2e;padding:12px 16px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                <div>
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#555">§ 14 GefStoffV · TRGS 555</div>
                    <div style="font-size:1.25rem;font-weight:700;margin-top:2px">{{ $sektionen['bezeichnung'] }}</div>
                    @if ($sektionen['arbeitsbereich'])
                        <div style="font-size:.85rem;margin-top:4px;color:#333">Arbeitsbereich: {{ $sektionen['arbeitsbereich'] }}</div>
                    @endif
                    @if ($sektionen['lagerort'])
                        <div style="font-size:.85rem;color:#555">Lagerort: {{ $sektionen['lagerort'] }}</div>
                    @endif
                </div>
                <div style="text-align:right;font-size:.8rem;color:#555">
                    <div>Stand: {{ $sektionen['stand'] }}</div>
                    <div style="margin-top:4px">Unterweisungsintervall: {{ $sektionen['unterweisung_intervall'] }} Monate</div>
                </div>
            </div>
        </div>

        {{-- Signalwort-Banner --}}
        @if ($sektionen['signalwort'])
            <div style="text-align:center;padding:8px;margin-bottom:16px;font-size:1.1rem;font-weight:700;
                background:{{ $sektionen['signalwort'] === 'Gefahr' ? '#c0392b' : '#e67e22' }};
                color:#fff;border-radius:4px;letter-spacing:.1em;text-transform:uppercase">
                ⚠ {{ $sektionen['signalwort'] }}
            </div>
        @endif

        {{-- GHS-Piktogramme --}}
        @if (!empty($sektionen['piktogramme']))
            <div style="margin-bottom:14px">
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600;color:#555;margin-bottom:4px">GHS-Piktogramme</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    @foreach ($sektionen['piktogramme'] as $p)
                        <span style="background:#f5f5f5;border:1px solid #ccc;border-radius:3px;padding:2px 8px;font-size:.8rem">{{ $p }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Abschnitt 1: Bezeichnung + Gefährliche Eigenschaften --}}
        <div style="border:1px solid #ddd;border-radius:4px;margin-bottom:10px;overflow:hidden">
            <div style="background:#1a1a2e;color:#fff;padding:6px 12px;font-weight:700;font-size:.8rem">
                1 — Gefährliche Eigenschaften (§ 14 Abs. 1 Nr. 2 GefStoffV · TRGS 555 Abschnitt 3.2)
            </div>
            <div style="padding:10px 12px;font-size:.85rem">
                @if (!empty($sektionen['h_saetze']))
                    <div style="margin-bottom:6px">
                        <strong>H-Sätze (Gefahrenhinweise):</strong><br>
                        {{ implode(' · ', $sektionen['h_saetze']) }}
                    </div>
                @else
                    <span class="muted">Keine H-Sätze erfasst.</span>
                @endif
                @if (!empty($sektionen['p_saetze']))
                    <div>
                        <strong>P-Sätze (Sicherheitshinweise):</strong><br>
                        {{ implode(' · ', $sektionen['p_saetze']) }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Abschnitt 2: Schutzmaßnahmen --}}
        <div style="border:1px solid #ddd;border-radius:4px;margin-bottom:10px;overflow:hidden">
            <div style="background:#1a1a2e;color:#fff;padding:6px 12px;font-weight:700;font-size:.8rem">
                2 — Schutzmaßnahmen und Verhaltensregeln (TRGS 555 Abschnitt 3.3)
            </div>
            <div style="padding:10px 12px;font-size:.85rem;white-space:pre-wrap">{{ $sektionen['schutzmassnahmen'] ?? 'Keine spezifischen Schutzmaßnahmen erfasst — P-Sätze (Abschnitt 1) beachten.' }}</div>
        </div>

        {{-- Abschnitt 3: Verhalten bei Störungen/Unfällen --}}
        <div style="border:1px solid #ddd;border-radius:4px;margin-bottom:10px;overflow:hidden">
            <div style="background:#c0392b;color:#fff;padding:6px 12px;font-weight:700;font-size:.8rem">
                3 — Verhalten bei Störungen und Unfällen (TRGS 555 Abschnitt 3.4)
            </div>
            <div style="padding:10px 12px;font-size:.85rem;white-space:pre-wrap">{{ $sektionen['stoerfall'] ?? 'Keine Störfall-Maßnahmen erfasst.' }}</div>
        </div>

        {{-- Abschnitt 4: Erste Hilfe --}}
        <div style="border:1px solid #ddd;border-radius:4px;margin-bottom:10px;overflow:hidden">
            <div style="background:#c0392b;color:#fff;padding:6px 12px;font-weight:700;font-size:.8rem">
                4 — Erste-Hilfe-Maßnahmen (TRGS 555 Abschnitt 3.5)
            </div>
            <div style="padding:10px 12px;font-size:.85rem;white-space:pre-wrap">{{ $sektionen['erste_hilfe'] ?? 'Keine Erste-Hilfe-Maßnahmen erfasst.' }}</div>
        </div>

        {{-- Abschnitt 5: Sachgerechte Entsorgung --}}
        <div style="border:1px solid #ddd;border-radius:4px;margin-bottom:10px;overflow:hidden">
            <div style="background:#1a1a2e;color:#fff;padding:6px 12px;font-weight:700;font-size:.8rem">
                5 — Sachgerechte Entsorgung (TRGS 555 Abschnitt 3.6)
            </div>
            <div style="padding:10px 12px;font-size:.85rem;white-space:pre-wrap">{{ $sektionen['entsorgung'] ?? 'Keine Entsorgungshinweise erfasst.' }}</div>
        </div>

        {{-- Fußzeile --}}
        <div style="margin-top:16px;padding:10px;background:#f9f9f9;border-radius:4px;font-size:.75rem;color:#555;border-left:3px solid #e67e22">
            <strong>§ 14 Abs. 2 GefStoffV:</strong> Jährliche Unterweisung der Beschäftigten auf Basis dieser Betriebsanweisung
            (Intervall: {{ $sektionen['unterweisung_intervall'] }} Monate) — Nachweis über <a href="{{ route('arbeitsschutz.nachweise') }}">Arbeitsschutz-Nachweise</a>.
        </div>
    </div>
</div>
