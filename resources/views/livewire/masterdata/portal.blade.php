<div>
    <div class="page-head">
        <div><p class="kicker">Mein Bereich</p><h1>Vertretung — meine Bewohner:innen</h1>
            <p class="lead">Read-only-Einsicht in die Daten Ihrer Aufgabenkreise (§ 1815 BGB), Ihre
                Informations-/Beteiligungsrechte (§ 1821) und Ihre Berichtspflicht (§ 1863).</p></div>
    </div>

    @forelse ($items as $it)
        @php $v = $it['vertretung']; $r = $it['resident']; @endphp
        <div class="card">
            <div class="card-head">
                <h3>{{ $r->name }}</h3>
                <span class="badge gray">{{ $v->typ->label() }}</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
                @forelse ($v->aufgabenkreiseEnums() as $k)
                    <span class="badge gray" style="font-size:.75em">{{ $k->label() }}</span>
                @empty
                    <span class="muted">keine Aufgabenkreise hinterlegt</span>
                @endforelse
            </div>

            @if ($v->naechsterBericht())
                <p style="margin:0 0 8px">
                    <span class="badge {{ $v->berichtAmpel() }}">Bericht fällig {{ $v->naechsterBericht()->format('d.m.Y') }}</span>
                    <span class="muted">— Ihre Berichtspflicht ans Betreuungsgericht (§ 1863 BGB)</span>
                </p>
            @endif

            @if ($it['diagnosen']->isNotEmpty() || $it['allergien']->isNotEmpty())
                <div class="qm-item">
                    <b>Gesundheitssorge</b>
                    @if ($it['diagnosen']->isNotEmpty())
                        <p class="muted" style="margin:2px 0">Diagnosen:
                            @foreach ($it['diagnosen'] as $d){{ $d->icdCode?->code }} {{ $d->icdCode?->bezeichnung }}@if (! $loop->last); @endif @endforeach
                        </p>
                    @endif
                    @if ($it['allergien']->isNotEmpty())
                        <p class="muted" style="margin:2px 0">Allergien: @foreach ($it['allergien'] as $a){{ $a->substanz }}@if (! $loop->last), @endif @endforeach</p>
                    @endif
                </div>
            @endif

            @if ($it['saldo'] !== null)
                <div class="qm-item"><b>Vermögenssorge</b>
                    <p class="muted" style="margin:2px 0">Barbetrag/Treuhand-Saldo: {{ number_format($it['saldo'], 2, ',', '.') }} €</p>
                </div>
            @endif

            @if ($it['posteingang']->isNotEmpty())
                <div class="qm-item"><b>Posteingang</b>
                    @foreach ($it['posteingang'] as $p)
                        <p class="muted" style="margin:2px 0">{{ $p->datum->format('d.m.Y') }} · {{ $p->titel }}</p>
                    @endforeach
                </div>
            @endif

            <div class="card-head" style="margin-top:8px"><h3 style="font-size:1em">Ereignisse mit Informationsrecht</h3></div>
            @forelse ($it['ereignisse'] as $e)
                <div class="qm-anf">
                    <span class="badge {{ $e->ampel() }}">{{ $e->datum->format('d.m.Y') }}</span>
                    <span class="badge gray" style="font-size:.75em">{{ $e->kategorie->label() }}</span>
                    <b>{{ $e->titel }}</b>
                    @if ($e->informiert_am)<span class="muted">· informiert {{ $e->informiert_am->format('d.m.Y') }}</span>@endif
                </div>
            @empty
                <p class="empty">Keine Ereignisse.</p>
            @endforelse
        </div>
    @empty
        <div class="card"><p class="empty">Ihrem Konto ist aktuell keine Vertretung zugeordnet. Bitte wenden Sie sich an die Einrichtungsleitung.</p></div>
    @endforelse
</div>
