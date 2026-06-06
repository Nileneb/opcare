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
            <nav class="app-nav app-nav-controlling">
                <a href="{{ route('controlling') }}" @class(['is-active' => request()->routeIs('controlling') || request()->routeIs('quality.report')])>Controlling</a>
                <a href="{{ route('quality.qm') }}" @class(['is-active' => request()->routeIs('quality.qm')])>QM-Checkliste</a>
                <a href="{{ route('qdvs.export') }}" @class(['is-active' => request()->routeIs('qdvs.export')])>QDVS-Export</a>
                <a href="{{ route('arbeitsschutz.nachweise') }}" @class(['is-active' => request()->routeIs('arbeitsschutz.nachweise')])>Arbeitsschutz</a>
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
            </nav>
        @endif
        @can('manage', \App\Domains\Scheduling\Models\Shift::class)
            <nav class="app-nav app-nav-dienstplan">
                <a href="{{ route('dienstplan') }}" @class(['is-active' => request()->routeIs('dienstplan')])>Dienstplan</a>
                <a href="{{ route('arbeitsrecht') }}" @class(['is-active' => request()->routeIs('arbeitsrecht')])>Arbeitsrecht</a>
            </nav>
        @endcan
        @unless ($istPortal)
        <nav class="app-nav app-nav-kalender">
            <a href="{{ route('kalender') }}" @class(['is-active' => request()->routeIs('kalender')])>Kalender</a>
            <a href="{{ route('zeiterfassung') }}" @class(['is-active' => request()->routeIs('zeiterfassung')])>Zeiterfassung</a>
            <a href="{{ route('wunschdienstplan') }}" @class(['is-active' => request()->routeIs('wunschdienstplan')])>Wunschdienst</a>
            <a href="{{ route('tauschboerse') }}" @class(['is-active' => request()->routeIs('tauschboerse')])>Tauschbörse</a>
            <a href="{{ route('energiebarometer') }}" @class(['is-active' => request()->routeIs('energiebarometer')])>Energie</a>
            <a href="{{ route('haustechnik') }}" @class(['is-active' => request()->routeIs('haustechnik')])>Haustechnik</a>
            <a href="{{ route('medizinprodukte') }}" @class(['is-active' => request()->routeIs('medizinprodukte')])>Medizinprodukte</a>
            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft', 'kueche', 'haustechnik', 'buchhaltung']))
                <a href="{{ route('quality.beschwerden') }}" @class(['is-active' => request()->routeIs('quality.beschwerden')])>Beschwerden</a>
            @endif
            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'kueche']))
                <a href="{{ route('kueche') }}" @class(['is-active' => request()->routeIs('kueche')])>Küche</a>
            @endif
        </nav>
        @endunless
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
            <nav class="app-nav app-nav-medikation-stamm">
                <a href="{{ route('medikation.stammdaten') }}" @class(['is-active' => request()->routeIs('medikation.stammdaten')])>Medikationsstamm</a>
                <a href="{{ route('medikation.btm') }}" @class(['is-active' => request()->routeIs('medikation.btm')])>BtM-Nachweis</a>
            </nav>
        @endif
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'buchhaltung']))
            <nav class="app-nav app-nav-finanzen">
                <a href="{{ route('buchhaltung') }}" @class(['is-active' => request()->routeIs('buchhaltung')])>Buchhaltung</a>
                <a href="{{ route('taschengeld') }}" @class(['is-active' => request()->routeIs('taschengeld')])>Taschengeldkasse</a>
            </nav>
        @endif
        @if (auth()->user()?->hasAnyRole(['admin', 'super-admin']))
            <nav class="app-nav app-nav-admin">
                <a href="{{ route('admin.tenants') }}" @class(['is-active' => request()->routeIs('admin.tenants')])>Einrichtungen</a>
                <a href="{{ route('admin.users') }}" @class(['is-active' => request()->routeIs('admin.users')])>Benutzer</a>
            </nav>
        @endif
        <div class="app-user">
            @auth @livewire('notification-bell') @endauth
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
