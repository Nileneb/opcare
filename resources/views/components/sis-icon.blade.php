@props(['name', 'size' => 24, 'stroke' => 1.75])
@php
    $filled = in_array($name, ['heart', 'play'], true);
    $paths = [
        'kognition' => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v6A2.5 2.5 0 0 1 17.5 15H10l-4 4v-4H6.5A2.5 2.5 0 0 1 4 12.5z" /><path d="M9 9.5h6M9 12h3.5" />',
        'mobilitaet' => '<circle cx="13" cy="4.5" r="1.6" /><path d="M12.5 8.2 10 11l1.8 2.2.7 5.6M12.5 8.2 16 9.5M12.5 8.2 8.5 9M11.8 13.2 8 18" />',
        'krankheit' => '<path d="M12 20S4 14.5 4 9.2C4 6.3 6.2 4.5 8.5 4.5c1.6 0 3 .9 3.5 2 .5-1.1 1.9-2 3.5-2C20 4.5 20 6.3 20 9.2c0 1-.3 2-.8 2.9" /><path d="M11 12.5h2l1-2 1.6 3.2 1-1.2h2.4" />',
        'selbstversorgung' => '<path d="M7.4 3.5v4.1a1.7 1.7 0 0 0 3.4 0V3.5" /><path d="M9.1 3.5v17" /><path d="M15.7 3.5c-1.25 0-2 1.7-2 3.7s.75 2.9 2 2.9v10.4" />',
        'soziales' => '<circle cx="8.5" cy="8" r="2.5" /><path d="M4 18v-1c0-2 2-3.2 4.5-3.2S13 15 13 17v1" /><circle cx="16" cy="9" r="2.1" /><path d="M14 13.6c2.2-.3 6 .5 6 3.4v1" />',
        'haushalt' => '<path d="M4 11 12 4.5 20 11" /><path d="M6 9.7V19h12V9.7" /><path d="M10.5 19v-4.5h3V19" />',
        'mic' => '<rect x="9" y="3" width="6" height="11" rx="3" /><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v3M9 21h6" />',
        'sparkles' => '<path d="M12 3.5 13.6 8 18 9.6 13.6 11.2 12 15.7 10.4 11.2 6 9.6 10.4 8z" /><path d="M18.5 14.5l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7zM5 14l.5 1.4 1.4.5-1.4.5L5 17.8l-.5-1.4L3 15.9l1.5-.5z" />',
        'check' => '<path d="M5 12.5 10 17.5 19 6.5" />',
        'checkCircle' => '<circle cx="12" cy="12" r="8.5" /><path d="M8 12.2 11 15.2 16.2 9" />',
        'x' => '<path d="M6 6 18 18M18 6 6 18" />',
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'chevronLeft' => '<path d="M14.5 5 8 12l6.5 7" />',
        'arrowRight' => '<path d="M5 12h13M13 6l6 6-6 6" />',
        'arrowsLR' => '<path d="M9 8 5 12l4 4M5 12h14M15 8l4 4-4 4" />',
        'search' => '<circle cx="11" cy="11" r="6.5" /><path d="M16 16 20.5 20.5" />',
        'alert' => '<path d="M12 4 21 19.5H3z" /><path d="M12 10v4.5M12 17.2v.2" />',
        'clock' => '<circle cx="12" cy="12" r="8.5" /><path d="M12 7v5.2l3.4 2" />',
        'user' => '<circle cx="12" cy="8" r="3.4" /><path d="M5 20v-1c0-3 3-4.6 7-4.6s7 1.6 7 4.6v1" />',
        'target' => '<circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="12" r="4.5" /><circle cx="12" cy="12" r="0.8" />',
        'info' => '<circle cx="12" cy="12" r="8.5" /><path d="M12 11v5M12 8v.2" />',
    ];
    $body = $paths[$name] ?? '';
@endphp
<svg {{ $attributes->merge(['class' => 'sis-icon']) }}
     width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="{{ $filled ? 'currentColor' : 'none' }}"
     stroke="{{ $filled ? 'none' : 'currentColor' }}"
     stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true">{!! $body !!}</svg>
