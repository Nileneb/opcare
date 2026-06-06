<div>
    <div class="page-head">
        <div><p class="kicker">Hauswirtschaft · Küche</p><h1>Küche & Verpflegung</h1>
            <p class="lead">Diäten der Bewohner im Blick und Speiseplan mit Allergenkennzeichnung (LMIV).</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Diäten & Lebensmittelallergien</h3><span class="badge gray">{{ $bewohner->count() }} Bewohner</span></div>
        @forelse ($bewohner as $r)
            <div class="qm-item">
                <div class="qm-anf">
                    <b>{{ $r->name }}</b>
                    @foreach ($r->allergies as $a)
                        <span class="badge red" title="{{ $a->reaktion }}">⚠ {{ $a->substanz }}@if ($a->kritikalitaet) ({{ $a->kritikalitaet }})@endif</span>
                    @endforeach
                    @foreach ($r->statusObservations as $o)
                        <span class="badge amber">{{ $service->kostformLabel($o) }}</span>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="empty">Keine besonderen Diäten oder Lebensmittelallergien erfasst.</p>
        @endforelse
    </div>

    <div class="card">
        <div class="card-head"><h3>Speiseplan</h3>
            <div class="plan-nav" style="margin:0">
                <button class="btn btn-ghost btn-sm" wire:click="tag(-1)">← Tag</button>
                <button class="btn btn-ghost btn-sm" wire:click="$set('datum', '{{ today()->toDateString() }}')">Heute</button>
                <button class="btn btn-ghost btn-sm" wire:click="tag(1)">Tag →</button>
                <b style="margin-left:6px">{{ $datumLabel }}</b>
            </div>
        </div>

        @foreach ($mahlzeiten as $mz)
            @php $items = $gerichte->where('mahlzeit', $mz); @endphp
            @if ($items->isNotEmpty())
                <div class="kueche-mz">
                    <h4>{{ $mz->label() }}</h4>
                    @foreach ($items as $g)
                        <div class="qm-item">
                            <div class="qm-anf">
                                <b>{{ $g->bezeichnung }}</b>
                                @foreach ($g->allergeneEnum() as $al)<span class="badge gray">{{ $al->label() }}</span>@endforeach
                                <button class="btn btn-ghost btn-sm" wire:click="gerichtEntfernen({{ $g->id }})" wire:confirm="Gericht entfernen?" style="margin-left:auto">✕</button>
                            </div>
                            @if (! empty($betroffenePro[$g->id]))
                                <div class="kueche-warn">
                                    ⚠ Betrifft:
                                    @foreach ($betroffenePro[$g->id] as $b)
                                        <span class="badge red">{{ $b['resident']->name }} — {{ $b['allergen'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach

        <form wire:submit="gerichtAnlegen" style="margin-top:14px;border-top:1px solid var(--line-cool);padding-top:14px">
            <div class="form-row">
                <div class="field"><label>Mahlzeit</label><select wire:model="g_mahlzeit">@foreach ($mahlzeiten as $mz)<option value="{{ $mz->value }}">{{ $mz->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Gericht *</label><input type="text" wire:model="g_bezeichnung" placeholder="z. B. Seelachsfilet mit Kartoffeln" />@error('g_bezeichnung')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Allergene (LMIV)</label>
                <div class="kueche-allergene">
                    @foreach ($allergene as $al)
                        <label class="kueche-chk"><input type="checkbox" value="{{ $al->value }}" wire:model="g_allergene" /> {{ $al->label() }}</label>
                    @endforeach
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Gericht</button>
        </form>
    </div>
</div>
