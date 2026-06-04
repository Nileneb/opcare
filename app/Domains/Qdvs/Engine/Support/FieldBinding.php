<?php

namespace App\Domains\Qdvs\Engine\Support;

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use Closure;

class FieldBinding
{
    /**
     * @param  Closure(QdvsResidentPackage): mixed  $accessor  liefert den Rohwert aus dem Paket
     * @param  string  $kind  scalar|int|decimal|date|list — steuert Casting bei der Auswertung
     * @param  null|Closure(mixed): mixed  $transform  semantischer Adapter (z. B. PFLEGEGRAD 1–5 → DAS 0/1)
     */
    public function __construct(
        public Closure $accessor,
        public string $kind = 'scalar',
        public ?Closure $transform = null,
    ) {}
}
