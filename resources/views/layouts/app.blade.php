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
    @livewireStyles
</head>
<body>
    @php
        $nav = [
            ['route' => 'overview', 'label' => 'Übersicht'],
            ['route' => 'bewohner', 'label' => 'Bewohner'],
            ['route' => 'spracherfassung', 'label' => 'Spracherfassung'],
            ['route' => 'einrichtung', 'label' => 'Stammdaten'],
            ['route' => 'pflegeplanung', 'label' => 'SIS-Board'],
        ];
        $u = auth()->user();
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
        <div class="app-user">
            <span class="app-demo-badge" title="Kein Login nötig — Demo-Modus mit vollem Zugriff">Demo-Modus</span>
            <div class="who">
                <b>{{ $u?->name ?? 'Gast' }}</b>
                <span>Frühdienst · Wohnbereich Aprath</span>
            </div>
            <div class="app-avatar">{{ $u ? \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : '–' }}</div>
        </div>
    </header>

    <main class="app-main">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
