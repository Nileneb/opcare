<div>
    <div class="page-head">
        <div><p class="kicker">Qualität · Beschwerdemanagement</p><h1>Beschwerden & Gewaltschutz</h1>
            <p class="lead">Eingang erfassen, bearbeiten und an die betroffene Abteilung weiterleiten — anonym oder namentlich, je nach Wahl des Melders (§ 113 SGB XI, Landes-WTG, Gewaltschutz § 5 SGB XI).</p></div>
        @if ($offen > 0)
            <span class="badge amber" style="align-self:center">{{ $offen }} offen / dringlich</span>
        @else
            <span class="badge green" style="align-self:center">nichts Offenes</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Eingang erfassen</h3><span class="badge gray">§ 113 SGB XI</span></div>
        <form wire:submit="erfassen">
            <div class="form-row-3">
                <div class="field"><label>Betreff *</label><input type="text" wire:model="b_titel" placeholder="kurz & sachlich" />@error('b_titel')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Kategorie</label><select wire:model="b_kategorie">@foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Betroffener Bereich</label><select wire:model="b_bereich">@foreach ($bereiche as $b)<option value="{{ $b->value }}">{{ $b->label() }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label>Beschreibung *</label><textarea wire:model="b_beschreibung" rows="3"></textarea>@error('b_beschreibung')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="form-row-3">
                <div class="field"><label>Quelle</label><select wire:model="b_quelle">@foreach ($quellen as $q)<option value="{{ $q->value }}">{{ $q->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Sichtbarkeit des Melders *</label><select wire:model="b_sichtbarkeit"><option value="namentlich">namentlich</option><option value="anonym">anonym</option></select>
                    <span class="muted" style="font-size:.78em">anonym ⇒ Identität wird nie gespeichert und nie weitergeleitet</span></div>
                <div class="field"><label>Name des Melders (wenn namentlich)</label><input type="text" wire:model="b_melder_name" placeholder="optional, z. B. Angehörige:r" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Betroffene:r Bewohner:in</label><select wire:model="b_resident"><option value="">– keine:r –</option>@foreach ($bewohner as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
                <div class="field"><label>Bearbeitungsfrist</label><input type="date" wire:model="b_frist" /></div>
                <div class="field"><label>Schweregrad (bei Gewaltvorfall) *</label><select wire:model="b_schweregrad"><option value="">–</option><option value="niedrig">niedrig</option><option value="mittel">mittel</option><option value="hoch">hoch</option></select>@error('b_schweregrad')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <button class="btn btn-primary btn-sm">+ Eingang erfassen</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Vorgänge</h3>@unless ($darfVerwalten)<span class="badge gray">an Ihren Bereich weitergeleitet / von Ihnen gemeldet</span>@endunless</div>
        <table class="data">
            <thead><tr><th>Betreff</th><th>Kategorie</th><th>Bereich</th><th>Melder</th><th>Status</th><th>Frist</th></tr></thead>
            <tbody>
                @forelse ($beschwerden as $x)
                    <tr wire:click="select({{ $x->id }})" style="cursor:pointer" @class(['is-active' => $beschwerde && $beschwerde->id === $x->id])>
                        <td><b>{{ $x->titel }}</b>
                            @if ($x->kategorie->istGewalt())<span class="badge {{ $x->ampel() }}">Gewaltschutz</span>@endif
                        </td>
                        <td>{{ $x->kategorie->label() }}</td>
                        <td>{{ $x->bereich->label() }}</td>
                        <td>{{ $x->melderAnzeige() }}</td>
                        <td><span class="badge {{ $x->ampel() }}">{{ $x->status->label() }}</span></td>
                        <td>{{ $x->frist?->format('d.m.Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Keine Vorgänge.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($beschwerde)
        <div class="card">
            <div class="card-head">
                <h3>{{ $beschwerde->titel }}</h3>
                <span class="badge {{ $beschwerde->ampel() }}">{{ $beschwerde->status->label() }}</span>
            </div>
            <div class="grid-3">
                <div><span class="muted">Kategorie</span><br>{{ $beschwerde->kategorie->label() }}</div>
                <div><span class="muted">Bereich</span><br>{{ $beschwerde->bereich->label() }}</div>
                <div><span class="muted">Quelle</span><br>{{ $beschwerde->quelle->label() }}</div>
                <div><span class="muted">Melder</span><br>{{ $beschwerde->melderAnzeige() }} @if ($beschwerde->anonym())<span class="badge gray">anonym</span>@endif</div>
                <div><span class="muted">Eingang</span><br>{{ $beschwerde->eingang_am?->format('d.m.Y') }}</div>
                <div><span class="muted">Frist</span><br>{{ $beschwerde->frist?->format('d.m.Y') ?? '—' }}</div>
                @if ($beschwerde->resident)<div><span class="muted">Betroffene:r</span><br>{{ $beschwerde->resident->name }}</div>@endif
                @if ($beschwerde->schweregrad)<div><span class="muted">Schweregrad</span><br>{{ ucfirst($beschwerde->schweregrad) }}</div>@endif
                @if ($beschwerde->bearbeiter)<div><span class="muted">Bearbeiter:in</span><br>{{ $beschwerde->bearbeiter->name }}</div>@endif
            </div>
            <p style="margin-top:8px">{{ $beschwerde->beschreibung }}</p>
            @if ($beschwerde->ergebnis)<p class="muted"><b>Ergebnis:</b> {{ $beschwerde->ergebnis }}</p>@endif

            @if ($darfVerwalten && $beschwerde->offen())
                <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                    @if ($beschwerde->status->value === 'eingegangen')<button class="btn btn-ghost btn-sm" wire:click="inBearbeitung">In Bearbeitung nehmen</button>@endif
                </div>
            @endif
        </div>

        @if ($beschwerde->kategorie->istGewalt())
            <div class="card">
                <div class="card-head"><h3>Gewaltschutz — Sofortmaßnahme</h3><span class="badge {{ $beschwerde->sofortmassnahme ? 'green' : 'red' }}">{{ $beschwerde->sofortmassnahme ? 'dokumentiert' : 'offen' }}</span></div>
                @if ($beschwerde->sofortmassnahme)<p>{{ $beschwerde->sofortmassnahme }}</p>@endif
                @if (($darfVerwalten || $darfBereich) && $beschwerde->offen())
                    <form wire:submit="sofortmassnahmeSetzen" style="margin-top:8px">
                        <div class="field"><label>Sofortmaßnahme (Schutz der betroffenen Person) *</label><textarea wire:model="sofort_text" rows="2"></textarea>@error('sofort_text')<span class="err">{{ $message }}</span>@enderror</div>
                        <button class="btn btn-primary btn-sm">Sofortmaßnahme dokumentieren</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($darfVerwalten && $beschwerde->offen())
            <div class="card">
                <div class="card-head"><h3>An Abteilung weiterleiten</h3></div>
                <form wire:submit="weiterleiten">
                    <div class="form-row-3">
                        <div class="field"><label>Zielbereich</label><select wire:model="w_bereich">@foreach ($bereiche as $b)<option value="{{ $b->value }}">{{ $b->label() }}</option>@endforeach</select></div>
                        <div class="field"><label>Anonym weiterleiten?</label>
                            @if ($beschwerde->anonym())
                                <p class="muted">Melder hat Anonymität gewählt — wird erzwungen.</p>
                            @else
                                <label style="font-weight:400"><input type="checkbox" wire:model="w_anonym" /> Melder dem Empfänger verbergen</label>
                            @endif
                        </div>
                    </div>
                    <div class="field"><label>Hinweis an die Abteilung</label><textarea wire:model="w_text" rows="2" placeholder="z. B. Bitte um Stellungnahme bis …"></textarea></div>
                    <button class="btn btn-primary btn-sm">Weiterleiten & benachrichtigen</button>
                </form>
            </div>
        @endif

        <div class="card">
            <div class="card-head"><h3>Verlauf</h3></div>
            @forelse ($vorgaenge as $v)
                <div class="qm-anf">
                    <span class="badge gray">{{ $v->art->label() }}</span>
                    @if ($v->art->value === 'weiterleitung')<span class="badge amber">→ {{ \App\Domains\Quality\Enums\BeschwerdeBereich::from($v->an_bereich)->label() }}</span>@if ($v->anonym)<span class="badge gray">anonym</span>@endif @endif
                    <span>{{ $v->text }}</span>
                    <span class="muted" style="margin-left:auto">{{ $v->autor?->name ?? 'System' }} · {{ $v->created_at?->format('d.m.Y H:i') }}</span>
                </div>
            @empty
                <p class="empty">Noch keine Vorgänge dokumentiert.</p>
            @endforelse

            @if (($darfVerwalten || $darfBereich) && $beschwerde->offen())
                <form wire:submit="vorgangHinzufuegen" style="margin-top:12px">
                    <div class="form-row-2">
                        <div class="field"><label>Art</label><select wire:model="v_art"><option value="stellungnahme">Stellungnahme</option><option value="massnahme">Maßnahme</option><option value="notiz">Notiz</option></select></div>
                        <div class="field"><label>Text *</label><input type="text" wire:model="v_text" />@error('v_text')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                    <button class="btn btn-ghost btn-sm">+ Vorgang</button>
                </form>
            @endif
        </div>

        @if ($darfVerwalten && $beschwerde->offen())
            <div class="card">
                <div class="card-head"><h3>Abschluss</h3></div>
                <form>
                    <div class="field"><label>Ergebnis / Begründung *</label><textarea wire:model="erg_text" rows="2"></textarea>@error('erg_text')<span class="err">{{ $message }}</span>@enderror</div>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn-primary btn-sm" wire:click="erledigen">Als erledigt schließen</button>
                        <button class="btn btn-ghost btn-sm" wire:click="ablehnen">Ablehnen</button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
