<div>
    <div class="page-head">
        <div>
            <p class="kicker">Datenauswertungsstelle</p>
            <h1>QDVS-Export</h1>
            <p class="lead">Qualitätsindikatoren-Daten zum Stichtag an die DAS exportieren.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" style="margin-bottom:var(--space-4)">{{ session('status') }}</div>
    @endif

    <div class="card" style="margin-bottom:var(--space-5)">
        <div class="card-head"><h3>Neuer Export</h3></div>
        @if ($coverage)
            <p class="hint" style="padding:0 var(--space-3);color:var(--c-muted);font-size:.85rem">
                DAS-Plausibilitätsprüfung: <b>{{ $coverage['applicable'] }}</b> von {{ $coverage['total'] }} Regeln aktiv scharf geschaltet.
                @php($skip = $coverage['skipped'] ?? [])
                <span title="Felder, die OPCare (noch) nicht erhebt">{{ $skip['unmapped_field'] ?? 0 }} ⌀ Datenfeld</span>,
                <span title="datensatzübergreifende Regeln">{{ $skip['out_of_scope_aggregate'] ?? 0 }} aggregat</span>,
                <span title="komplexe Mehrfeld-Muster">{{ $skip['unknown_pattern'] ?? 0 }} ungestützt</span>.
            </p>
        @endif
        <form wire:submit="erstellen" style="padding:var(--space-3);display:flex;gap:var(--space-3);align-items:flex-end;flex-wrap:wrap">
            <div class="form-group">
                <label class="label" for="stichtag">Stichtag</label>
                <input id="stichtag" type="date" class="input @error('stichtag') is-invalid @enderror"
                    wire:model="stichtag" />
                @error('stichtag') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <label class="label" for="specKey">Spezifikation</label>
                <select id="specKey" class="input @error('specKey') is-invalid @enderror" wire:model="specKey">
                    @foreach ($specs as $key => $spec)
                        <option value="{{ $key }}">{{ $spec->label() }}</option>
                    @endforeach
                </select>
                @error('specKey') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Export erstellen</span>
                    <span wire:loading>Wird erstellt…</span>
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Letzte Exporte</h3></div>
        @if ($exports->isEmpty())
            <p class="empty" style="padding:var(--space-3)">Noch keine Exporte vorhanden.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Stichtag</th>
                        <th>Spezifikation</th>
                        <th>Status</th>
                        <th style="text-align:right">Bewohner</th>
                        <th style="text-align:right">Regeln</th>
                        <th>Erstellt</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exports as $export)
                        <tr>
                            <td>{{ $export->stichtag->format('d.m.Y') }}</td>
                            <td>{{ $export->spec }}</td>
                            <td>
                                @if ($export->status === 'exportiert')
                                    <span class="badge badge-ok">exportiert</span>
                                @elseif ($export->status === 'fehler')
                                    <span class="badge badge-error">fehler</span>
                                @elseif ($export->status === 'validiert')
                                    <span class="badge">validiert</span>
                                @else
                                    <span class="badge badge-warn">{{ $export->status }}</span>
                                @endif
                            </td>
                            <td style="text-align:right">{{ $export->bewohner_count }}</td>
                            <td style="text-align:right">
                                @if ($export->regel_coverage)
                                    <span title="aktiv geprüfte DAS-Regeln">{{ $export->regel_coverage['applicable'] }}/{{ $export->regel_coverage['total'] }}</span>
                                @else
                                    <span class="empty">—</span>
                                @endif
                            </td>
                            <td>{{ $export->created_at->format('d.m.Y H:i') }}</td>
                            <td>
                                @if ($export->status === 'exportiert')
                                    <a href="{{ route('qdvs.download', $export) }}" class="btn btn-ghost btn-sm">Download</a>
                                @elseif ($export->status === 'fehler' && !empty($export->fehler))
                                    <details style="font-size:.8rem">
                                        <summary class="btn btn-ghost btn-sm" style="cursor:pointer">Issues ({{ count($export->fehler) }})</summary>
                                        <ul style="margin:var(--space-2) 0 0 var(--space-3);padding:0;list-style:disc;color:var(--c-danger)">
                                            @foreach ($export->fehler as $issue)
                                                <li>
                                                    <b>{{ $issue['pseudonym'] }}</b>
                                                    · {{ $issue['feld'] }}:
                                                    {{ $issue['meldung'] }}
                                                    @if (($issue['schwere'] ?? '') === 'warnung')
                                                        <span class="badge badge-warn" style="font-size:.75rem">Warnung</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
