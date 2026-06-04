<?php

use App\Domains\Qdvs\Engine\Support\AssertNormalizer;

it('dekodiert XML-Entities und kollabiert Whitespace beim Normalisieren', function () {
    $n = new AssertNormalizer;

    expect($n->normalize('ERHEBUNGSDATUM/@value &lt; EINZUGSDATUM/@value'))
        ->toBe('ERHEBUNGSDATUM/@value < EINZUGSDATUM/@value')
        ->and($n->normalize('KOERPERGEWICHT/@value &gt;  500'))
        ->toBe('KOERPERGEWICHT/@value > 500');
});
