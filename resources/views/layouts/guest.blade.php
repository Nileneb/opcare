<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'Anmelden' }} — OPCare</title>
    <link rel="icon" href="{{ asset('sis/assets/heart.svg') }}" />
    <link rel="stylesheet" href="{{ asset('sis/css/colors_and_type.css') }}" />
    <link rel="stylesheet" href="{{ asset('sis/css/admin.css') }}" />
    @livewireStyles
    <style>
        body { display: grid; place-items: center; min-height: 100vh; background: radial-gradient(120% 90% at 50% 0%, var(--paper-2) 0%, var(--paper) 55%); }
        .auth-wrap { width: min(440px, 92vw); }
        .auth-brand { display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 22px; }
        .auth-brand img { height: 38px; }
        .auth-brand .name { font-weight: var(--fw-bold); color: var(--ink-700); font-size: 1.2em; line-height: 1.05; }
        .auth-brand .name small { display: block; font-size: 0.62em; color: var(--color-accent); font-weight: var(--fw-semibold); }
        .auth-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 30px 28px; box-shadow: var(--shadow-hover); }
        .auth-card h1 { font-size: 1.5em; font-weight: var(--fw-bold); margin: 0 0 4px; }
        .auth-card .sub { color: var(--color-fg-muted); margin: 0 0 22px; font-size: 0.95em; }
        .auth-alt { text-align: center; margin-top: 16px; font-size: 0.9em; color: var(--color-fg-muted); }
        .auth-alt a { color: var(--color-link); font-weight: var(--fw-semibold); text-decoration: none; }
        .auth-demo { margin-top: 18px; font-size: 0.82em; color: var(--color-fg-muted); background: var(--green-050); border: 1px solid var(--green-100); border-radius: var(--radius-md); padding: 10px 12px; }
        .auth-demo b { color: var(--green-800); }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-brand">
            <img src="{{ asset('sis/assets/logo.png') }}" alt="Bergische Diakonie" />
            <span class="name">OPCare<small>SIS® Pflegeplanung</small></span>
        </div>
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
