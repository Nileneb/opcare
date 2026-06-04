<div>
    <div class="page-head">
        <div>
            <p class="kicker">Konfiguration</p>
            <h1>Stammdaten & Einrichtung</h1>
            <p class="lead">Gebäudestruktur (Gebäude → Etage → Station → Zimmer) und tenant-weite Referenzdaten.</p>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="section-label">Gebäudestruktur</div>
    <div class="grid-4">
        <div class="card">
            <div class="card-head"><h3>Gebäude</h3></div>
            @forelse ($buildings as $b)<div class="chip">{{ $b->name }}</div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addBuilding" style="margin-top:12px">
                <div class="field"><input type="text" wire:model="b_name" placeholder="Name" />@error('b_name')<span class="err">{{ $message }}</span>@enderror</div>
                <button class="btn btn-ghost btn-sm">+ Gebäude</button>
            </form>
        </div>
        <div class="card">
            <div class="card-head"><h3>Etagen</h3></div>
            @forelse ($floors as $f)<div class="chip">{{ $f->name }} <span class="muted">· {{ $f->building?->name }}</span></div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addFloor" style="margin-top:12px">
                <div class="field"><select wire:model="f_building"><option value="">Gebäude…</option>@foreach ($buildings as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>@error('f_building')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="text" wire:model="f_name" placeholder="z. B. EG" />@error('f_name')<span class="err">{{ $message }}</span>@enderror</div>
                <button class="btn btn-ghost btn-sm">+ Etage</button>
            </form>
        </div>
        <div class="card">
            <div class="card-head"><h3>Stationen</h3></div>
            @forelse ($stations as $s)<div class="chip">{{ $s->name }} <span class="muted">· {{ $s->floor?->name }}</span></div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addStation" style="margin-top:12px">
                <div class="field"><select wire:model="s_floor"><option value="">Etage…</option>@foreach ($floors as $f)<option value="{{ $f->id }}">{{ $f->name }} ({{ $f->building?->name }})</option>@endforeach</select>@error('s_floor')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="text" wire:model="s_name" placeholder="z. B. Wohnbereich 1" />@error('s_name')<span class="err">{{ $message }}</span>@enderror</div>
                <button class="btn btn-ghost btn-sm">+ Station</button>
            </form>
        </div>
        <div class="card">
            <div class="card-head"><h3>Zimmer</h3></div>
            @forelse ($rooms as $room)<div class="chip">Zimmer {{ $room->nummer }} <span class="muted">· {{ $room->station?->name }} · {{ $room->betten }} B.</span></div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addRoom" style="margin-top:12px">
                <div class="field"><select wire:model="r_station"><option value="">Station…</option>@foreach ($stations as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>@error('r_station')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="text" wire:model="r_nummer" placeholder="Nummer" />@error('r_nummer')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="number" min="1" wire:model="r_betten" placeholder="Betten" /></div>
                <button class="btn btn-ghost btn-sm">+ Zimmer</button>
            </form>
        </div>
    </div>

    <div class="section-label" style="margin-top:8px">Referenzdaten</div>
    <div class="grid-3">
        <div class="card">
            <div class="card-head"><h3>ICD-10-Katalog</h3></div>
            @forelse ($icdCodes as $c)<div class="chip"><b>{{ $c->code }}</b> {{ $c->bezeichnung }}</div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addIcd" style="margin-top:12px">
                <div class="field"><input type="text" wire:model="icd_code" placeholder="Code z. B. F00.0" />@error('icd_code')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="text" wire:model="icd_bez" placeholder="Bezeichnung" />@error('icd_bez')<span class="err">{{ $message }}</span>@enderror</div>
                <button class="btn btn-ghost btn-sm">+ ICD-Code</button>
            </form>
        </div>
        <div class="card">
            <div class="card-head"><h3>Krankenkassen</h3></div>
            @forelse ($insurances as $i)<div class="chip"><b>{{ $i->name }}</b> <span class="muted">{{ $i->ik_nummer }}</span></div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addInsurance" style="margin-top:12px">
                <div class="field"><input type="text" wire:model="ins_name" placeholder="Name" />@error('ins_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="text" wire:model="ins_ik" placeholder="IK-Nummer (optional)" /></div>
                <button class="btn btn-ghost btn-sm">+ Kasse</button>
            </form>
        </div>
        <div class="card">
            <div class="card-head"><h3>Ärzt:innen</h3></div>
            @forelse ($physicians as $p)<div class="chip"><b>{{ $p->name }}</b> <span class="muted">{{ $p->fachrichtung }}</span></div>@empty<p class="empty">—</p>@endforelse
            <form wire:submit="addPhysician" style="margin-top:12px">
                <div class="field"><input type="text" wire:model="phys_name" placeholder="Name" />@error('phys_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><input type="text" wire:model="phys_fach" placeholder="Fachrichtung" /></div>
                <div class="field"><input type="text" wire:model="phys_kontakt" placeholder="Kontakt" /></div>
                <button class="btn btn-ghost btn-sm">+ Arzt/Ärztin</button>
            </form>
        </div>
    </div>
</div>
