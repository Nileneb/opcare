<?php

arch('Domänen hängen nicht von Http ab')
    ->expect('App\Domains')
    ->not->toUse('App\Http');

arch('Actions sind invokable oder haben handle()')
    ->expect('App\Domains\Masterdata\Actions')
    ->toHaveMethod('handle');

arch('keine debug-Funktionen')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
