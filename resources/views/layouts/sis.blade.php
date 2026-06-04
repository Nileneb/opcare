<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>SIS® Pflegeplanung — Bergische Diakonie · OPCare</title>
    <link rel="icon" href="{{ asset('sis/assets/heart.svg') }}" />
    <link rel="stylesheet" href="{{ asset('sis/css/colors_and_type.css') }}" />
    <link rel="stylesheet" href="{{ asset('sis/css/app.css') }}" />
    @livewireStyles
</head>
<body>
    <div class="sis-stage">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
