<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'OPCare' }} — OPCare</title>
    <link rel="icon" href="{{ asset('sis/assets/heart.svg') }}" />
    <link rel="stylesheet" href="{{ asset('sis/css/colors_and_type.css') }}" />
    <link rel="stylesheet" href="{{ asset('sis/css/admin.css') }}" />
    <script src="{{ asset('sis/js/voice.js') }}"></script>
    @vite('resources/js/app.js')
    @livewireStyles
</head>
<body>
    @php
        $u = auth()->user();
        // WHY(Portal-Schranke): Vertretungs-Konten (nur betreuer/angehoeriger) sehen ausschließlich „Mein Bereich".
        $istPortal = $u !== null && $u->hasAnyRole(['betreuer', 'angehoeriger'])
            && ! $u->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche',
                'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin']);
        $nav = $istPortal ? [['route' => 'portal', 'label' => 'Mein Bereich']] : [
            ['route' => 'overview', 'label' => 'Übersicht'],
            ['route' => 'bewohner', 'label' => 'Bewohner'],
            ['route' => 'einrichtung', 'label' => 'Stammdaten'],
            ['route' => 'pflegeplanung', 'label' => 'SIS-Board'],
        ];
        if (! $istPortal && ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft']))) {
            $nav[] = ['route' => 'betreuung', 'label' => 'Betreuung'];
            $nav[] = ['route' => 'praevention', 'label' => 'Prävention'];
        }
    @endphp
    <header class="app-top">
        <a class="app-brand" href="{{ route('overview') }}" style="text-decoration:none">
            <img src="{{ asset('sis/assets/logo.png') }}" alt="Bergische Diakonie" />
            <span class="name">OPCare<small>SIS® Pflegeplanung</small></span>
        </a>
        <nav class="app-nav">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])])>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        @if (auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'super-admin']))
            @php($qualActive = request()->routeIs('controlling','quality.report','quality.qm','qdvs.export','arbeitsschutz.nachweise','arbeitsschutz.gbu','personnel.fortbildung','hygiene','datenschutz','personnel.kompetenzen','personnel.berechtigungen','personnel.beauftragte','quality.fem','quality.gremien','vertretungen','heimrecht'))
            <div class="nav-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="nav-menu-btn {{ $qualActive ? 'is-active' : '' }}" @click="open = !open" :aria-expanded="open">
                    Qualität &amp; Recht <span class="nav-caret">▾</span>
                </button>
                <div class="nav-menu-panel" x-show="open" x-transition @click.outside="open = false" x-cloak>
                    <a href="{{ route('controlling') }}" @class(['is-active' => request()->routeIs('controlling') || request()->routeIs('quality.report')])>Controlling</a>
                    <a href="{{ route('quality.qm') }}" @class(['is-active' => request()->routeIs('quality.qm')])>QM-Checkliste</a>
                    <a href="{{ route('qdvs.export') }}" @class(['is-active' => request()->routeIs('qdvs.export')])>QDVS-Export</a>
                    <a href="{{ route('arbeitsschutz.nachweise') }}" @class(['is-active' => request()->routeIs('arbeitsschutz.nachweise')])>Arbeitsschutz-Nachweise</a>
                    <a href="{{ route('arbeitsschutz.gbu') }}" @class(['is-active' => request()->routeIs('arbeitsschutz.gbu')])>Gefährdungsbeurteilung</a>
                    <a href="{{ route('personnel.fortbildung') }}" @class(['is-active' => request()->routeIs('personnel.fortbildung')])>Fortbildung</a>
                    <a href="{{ route('hygiene') }}" @class(['is-active' => request()->routeIs('hygiene')])>Hygiene/MRE</a>
                    <a href="{{ route('datenschutz') }}" @class(['is-active' => request()->routeIs('datenschutz')])>Datenschutz</a>
                    <a href="{{ route('personnel.kompetenzen') }}" @class(['is-active' => request()->routeIs('personnel.kompetenzen')])>Skill-Baum</a>
                    <a href="{{ route('personnel.berechtigungen') }}" @class(['is-active' => request()->routeIs('personnel.berechtigungen')])>Berechtigungen</a>
                    <a href="{{ route('personnel.beauftragte') }}" @class(['is-active' => request()->routeIs('personnel.beauftragte')])>Beauftragte</a>
                    <a href="{{ route('quality.fem') }}" @class(['is-active' => request()->routeIs('quality.fem')])>FEM</a>
                    <a href="{{ route('quality.gremien') }}" @class(['is-active' => request()->routeIs('quality.gremien')])>Gremien</a>
                    <a href="{{ route('vertretungen') }}" @class(['is-active' => request()->routeIs('vertretungen')])>Vertretungen</a>
                    <a href="{{ route('heimrecht') }}" @class(['is-active' => request()->routeIs('heimrecht')])>Heimrecht</a>
                </div>
            </div>
        @endif
        @can('manage', \App\Domains\Scheduling\Models\Shift::class)
            @php($dienstActive = request()->routeIs('dienstplan','arbeitsrecht','spitzenzeiten'))
            <div class="nav-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="nav-menu-btn {{ $dienstActive ? 'is-active' : '' }}" @click="open = !open" :aria-expanded="open">
                    Dienstplan <span class="nav-caret">▾</span>
                </button>
                <div class="nav-menu-panel" x-show="open" x-transition @click.outside="open = false" x-cloak>
                    <a href="{{ route('dienstplan') }}" @class(['is-active' => request()->routeIs('dienstplan')])>Dienstplan</a>
                    <a href="{{ route('arbeitsrecht') }}" @class(['is-active' => request()->routeIs('arbeitsrecht')])>Arbeitsrecht</a>
                    <a href="{{ route('spitzenzeiten') }}" @class(['is-active' => request()->routeIs('spitzenzeiten')])>Spitzenzeiten</a>
                </div>
            </div>
        @endcan
        @unless ($istPortal)
            @php($votingActive = request()->routeIs('abstimmungen'))
            <a href="{{ route('abstimmungen') }}" @class(['is-active' => $votingActive])>Abstimmungen</a>
            @php($kalenderActive = request()->routeIs('kalender','zeiterfassung','wunschdienstplan','tauschboerse','energiebarometer','haustechnik','medizinprodukte','trinkwasser','brandschutz','quality.beschwerden','kueche','haccp','haccp.gefahrenanalyse','reinigungsplan'))
            <div class="nav-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="nav-menu-btn {{ $kalenderActive ? 'is-active' : '' }}" @click="open = !open" :aria-expanded="open">
                    Kalender &amp; Betrieb <span class="nav-caret">▾</span>
                </button>
                <div class="nav-menu-panel" x-show="open" x-transition @click.outside="open = false" x-cloak>
                    <a href="{{ route('kalender') }}" @class(['is-active' => request()->routeIs('kalender')])>Kalender</a>
                    <a href="{{ route('zeiterfassung') }}" @class(['is-active' => request()->routeIs('zeiterfassung')])>Zeiterfassung</a>
                    <a href="{{ route('wunschdienstplan') }}" @class(['is-active' => request()->routeIs('wunschdienstplan')])>Wunschdienst</a>
                    <a href="{{ route('tauschboerse') }}" @class(['is-active' => request()->routeIs('tauschboerse')])>Tauschbörse</a>
                    <a href="{{ route('energiebarometer') }}" @class(['is-active' => request()->routeIs('energiebarometer')])>Energie</a>
                    <a href="{{ route('haustechnik') }}" @class(['is-active' => request()->routeIs('haustechnik')])>Haustechnik</a>
                    <a href="{{ route('medizinprodukte') }}" @class(['is-active' => request()->routeIs('medizinprodukte')])>Medizinprodukte</a>
                    @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'haustechnik']))
                        <a href="{{ route('trinkwasser') }}" @class(['is-active' => request()->routeIs('trinkwasser')])>Trinkwasser</a>
                        <a href="{{ route('brandschutz') }}" @class(['is-active' => request()->routeIs('brandschutz')])>Brandschutz</a>
                    @endif
                    @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft', 'kueche', 'haustechnik', 'buchhaltung']))
                        <a href="{{ route('quality.beschwerden') }}" @class(['is-active' => request()->routeIs('quality.beschwerden')])>Beschwerden</a>
                    @endif
                    @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'kueche']))
                        <a href="{{ route('kueche') }}" @class(['is-active' => request()->routeIs('kueche')])>Küche</a>
                        <a href="{{ route('haccp') }}" @class(['is-active' => request()->routeIs('haccp')])>HACCP</a>
                        <a href="{{ route('haccp.gefahrenanalyse') }}" @class(['is-active' => request()->routeIs('haccp.gefahrenanalyse')])>Gefahrenanalyse</a>
                        <a href="{{ route('reinigungsplan') }}" @class(['is-active' => request()->routeIs('reinigungsplan')])>Reinigungsplan</a>
                    @endif
                </div>
            </div>
        @endunless
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
            @php($medikActive = request()->routeIs('medikation.stammdaten','medikation.btm'))
            <div class="nav-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="nav-menu-btn {{ $medikActive ? 'is-active' : '' }}" @click="open = !open" :aria-expanded="open">
                    Medikation <span class="nav-caret">▾</span>
                </button>
                <div class="nav-menu-panel" x-show="open" x-transition @click.outside="open = false" x-cloak>
                    <a href="{{ route('medikation.stammdaten') }}" @class(['is-active' => request()->routeIs('medikation.stammdaten')])>Medikationsstamm</a>
                    <a href="{{ route('medikation.btm') }}" @class(['is-active' => request()->routeIs('medikation.btm')])>BtM-Nachweis</a>
                </div>
            </div>
        @endif
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'buchhaltung']))
            @php($finanzActive = request()->routeIs('buchhaltung','inventur','pflegehilfsmittel','gefahrstoffe','belegerfassung','wareneingang-capture','regalzaehlung','rueckverfolgung','taschengeld','beschaffung','datenimport'))
            <div class="nav-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="nav-menu-btn {{ $finanzActive ? 'is-active' : '' }}" @click="open = !open" :aria-expanded="open">
                    WaWi &amp; Finanzen <span class="nav-caret">▾</span>
                </button>
                <div class="nav-menu-panel" x-show="open" x-transition @click.outside="open = false" x-cloak>
                    <a href="{{ route('buchhaltung') }}" @class(['is-active' => request()->routeIs('buchhaltung')])>Buchhaltung</a>
                    <a href="{{ route('inventur') }}" @class(['is-active' => request()->routeIs('inventur')])>Inventur</a>
                    <a href="{{ route('pflegehilfsmittel') }}" @class(['is-active' => request()->routeIs('pflegehilfsmittel')])>Pflegehilfsmittel</a>
                    <a href="{{ route('gefahrstoffe') }}" @class(['is-active' => request()->routeIs('gefahrstoffe')])>Gefahrstoffe</a>
                    <a href="{{ route('belegerfassung') }}" @class(['is-active' => request()->routeIs('belegerfassung')])>Beleg-Capture</a>
                    <a href="{{ route('wareneingang-capture') }}" @class(['is-active' => request()->routeIs('wareneingang-capture')])>Beleg→Wareneingang</a>
                    <a href="{{ route('regalzaehlung') }}" @class(['is-active' => request()->routeIs('regalzaehlung')])>Regalzählung</a>
                    <a href="{{ route('rueckverfolgung') }}" @class(['is-active' => request()->routeIs('rueckverfolgung')])>Rückverfolgung</a>
                    <a href="{{ route('taschengeld') }}" @class(['is-active' => request()->routeIs('taschengeld')])>Taschengeldkasse</a>
                    <a href="{{ route('beschaffung') }}" @class(['is-active' => request()->routeIs('beschaffung')])>Beschaffung</a>
                    <a href="{{ route('datenimport') }}" @class(['is-active' => request()->routeIs('datenimport')])>Datenimport</a>
                </div>
            </div>
        @endif
        @if (auth()->user()?->hasAnyRole(['admin', 'super-admin']))
            @php($adminActive = request()->routeIs('admin.tenants','admin.users','hr.invitations.create','hr.applications.index'))
            <div class="nav-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="nav-menu-btn {{ $adminActive ? 'is-active' : '' }}" @click="open = !open" :aria-expanded="open">
                    Admin <span class="nav-caret">▾</span>
                </button>
                <div class="nav-menu-panel" x-show="open" x-transition @click.outside="open = false" x-cloak>
                    <a href="{{ route('admin.tenants') }}" @class(['is-active' => request()->routeIs('admin.tenants')])>Einrichtungen</a>
                    <a href="{{ route('admin.users') }}" @class(['is-active' => request()->routeIs('admin.users')])>Benutzer</a>
                    <a href="{{ route('hr.invitations.create') }}" @class(['is-active' => request()->routeIs('hr.invitations.create')])>Einladungen</a>
                    <a href="{{ route('hr.applications.index') }}" @class(['is-active' => request()->routeIs('hr.applications.index')])>Bewerbungen</a>
                </div>
            </div>
        @endif
        @unless ($istPortal)
            <a href="{{ route('chat') }}" @class(['nav-menu-btn', 'is-active' => request()->routeIs('chat')])>💬 Chat</a>
        @endunless
        <div class="app-user">
            @auth @livewire('notification-bell') @livewire('communication.chat-glocke') @endauth
            @livewire('admin.tenant-switcher')
            <a href="{{ route('profile') }}" class="who" wire:navigate style="text-decoration:none;color:inherit">
                <b>{{ $u?->name ?? 'Gast' }}</b>
                <span>{{ $u?->tenant?->name ?? 'Wohnbereich' }}</span>
            </a>
            <div class="app-avatar">{{ $u ? \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : '–' }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Abmelden</button>
            </form>
        </div>
    </header>

    <main class="app-main">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
