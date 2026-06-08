<div>
    <div class="page-head">
        <div>
            <p class="kicker">Küche · Lebensmittelhygiene</p>
            <h1>Reinigungs- und Desinfektionsplan</h1>
            <p class="lead">Dokumentation von Reinigungsaufgaben nach VO (EG) 852/2004 Anhang II und LMHV. Fälligkeit, Nachweis und Ampelstatus je Aufgabe.</p>
        </div>
        @php
            $ueberfaelligGesamt = $aufgaben->filter(fn($a) => $a->faelligkeitsStatus() === 'rot')->count();
        @endphp
        @if ($ueberfaelligGesamt > 0)
            <span class="badge red" title="überfällige Reinigungsaufgaben">{{ $ueberfaelligGesamt }} {{ $ueberfaelligGesamt === 1 ? 'Aufgabe' : 'Aufgaben' }} überfällig</span>
        @endif
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Aufgaben-Plan --}}
    @if ($aufgaben->isEmpty())
        <div class="card"><p class="muted">Noch keine aktiven Reinigungsaufgaben erfasst. Bitte unten eine Aufgabe anlegen.</p></div>
    @else
        @foreach ($aufgaben as $aufgabe)
            @php
                $status = $aufgabe->faelligkeitsStatus();
                $naechste = $aufgabe->naechsteFaelligkeit();
                $ueberfaelligSeit = null;
                if ($status === 'rot' && $naechste !== null) {
                    $ueberfaelligSeit = (int) $naechste->diffInDays(today());
                }
            @endphp
            <div class="card" style="{{ $status === 'rot' ? 'border-left:4px solid #c0392b' : ($status === 'gelb' ? 'border-left:4px solid #e67e22' : '') }}">
                <div class="card-head">
                    <div>
                        <h3>{{ $aufgabe->bezeichnung }}</h3>
                        <span class="muted">
                            {{ $aufgabe->intervall->label() }}
                            @if ($aufgabe->bereich)&nbsp;·&nbsp; {{ $aufgabe->bereich }}@endif
                            @if ($aufgabe->verantwortlich)&nbsp;·&nbsp; {{ $aufgabe->verantwortlich }}@endif
                        </span>
                    </div>
                    @if ($status === 'rot')
                        <span class="badge red">überfällig{{ ($ueberfaelligSeit !== null && $ueberfaelligSeit > 0) ? "\u{00A0}seit {$ueberfaelligSeit} " . ($ueberfaelligSeit === 1 ? 'Tag' : 'Tagen') : '' }}</span>
                    @endif
                    @if ($status === 'gelb')
                        <span class="badge amber">bald fällig{{ $naechste ? "\u{00A0}am " . $naechste->format('d.m.Y') : '' }}</span>
                    @endif
                    @if ($status === 'gruen')
                        <span class="badge green">erledigt{{ $naechste ? "\u{00A0}· nächste Fälligkeit " . $naechste->format('d.m.Y') : '' }}</span>
                    @endif
                </div>

                {{-- Nachweis-Historie --}}
                @if ($aufgabe->nachweise->isNotEmpty())
                    <div style="margin-bottom:.75rem">
                        <h4 style="font-size:.85em;margin-bottom:.4rem;color:#666">Letzte Erledigungen</h4>
                        <table class="data" style="font-size:.85em">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Person</th>
                                    <th>Bemerkung</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($aufgabe->nachweise as $nachweis)
                                    <tr>
                                        <td>{{ $nachweis->erledigt_am->format('d.m.Y') }}</td>
                                        <td>{{ $nachweis->erlediger?->name ?? '—' }}</td>
                                        <td>{{ $nachweis->bemerkung ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Erledigt melden --}}
                <details style="margin-top:.5rem">
                    <summary class="btn btn-sm" style="cursor:pointer;display:inline-block">&#10003; Erledigt melden</summary>
                    <form wire:submit="erledigen({{ $aufgabe->id }})" style="margin-top:.75rem">
                        <div class="form-row-3">
                            <div class="field">
                                <label>Erledigungsdatum *</label>
                                <input type="date" wire:model="erledigt_am" max="{{ now()->format('Y-m-d') }}" />
                                @error('erledigt_am')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field" style="grid-column:span 2">
                                <label>Bemerkung (optional)</label>
                                <input type="text" wire:model="bemerkung" placeholder="z. B. Mittel, besondere Vorkommnisse" />
                                @error('bemerkung')<span class="err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm">Erledigung dokumentieren</button>
                    </form>
                </details>
            </div>
        @endforeach
    @endif

    {{-- Aufgabe anlegen --}}
    <div class="card">
        <div class="card-head">
            <h3>Aufgabe anlegen</h3>
            <span class="badge gray">VO (EG) 852/2004 Anhang II</span>
        </div>
        <form wire:submit="aufgabeSpeichern">
            <div class="form-row-3">
                <div class="field">
                    <label>Bezeichnung *</label>
                    <input type="text" wire:model="bezeichnung" placeholder="z. B. Arbeitsflächen reinigen" />
                    @error('bezeichnung')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Bereich (optional)</label>
                    <input type="text" wire:model="bereich" placeholder="z. B. Küche, Lager, Ausgabe" />
                    @error('bereich')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Intervall *</label>
                    <select wire:model="intervall">
                        <option value="">— bitte wählen —</option>
                        @foreach ($intervalle as $iv)
                            <option value="{{ $iv->value }}">{{ $iv->label() }}</option>
                        @endforeach
                    </select>
                    @error('intervall')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-row-3" style="margin-top:.5rem">
                <div class="field">
                    <label>Verantwortlich (optional)</label>
                    <input type="text" wire:model="verantwortlich" placeholder="z. B. Köchin, Küchenhilfe" />
                    @error('verantwortlich')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Aufgabe anlegen</button>
        </form>
    </div>

    <p class="muted" style="font-size:.8em;margin-top:1.5rem">
        Rechtsgrundlage: Verordnung (EG) Nr. 852/2004 Anhang II Kap. I Nr. 1 (Reinigung und Desinfektion),
        LMHV — Lebensmittelhygiene-Verordnung §§ 3/4.
        Dokumentationspflicht: Was / Womit / Wie / Wann / Wer.
    </p>
</div>
