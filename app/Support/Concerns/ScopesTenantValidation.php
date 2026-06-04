<?php

namespace App\Support\Concerns;

use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ScopesTenantValidation
{
    // WHY(IDOR): Laravels String-Regel `exists:tabelle,spalte` fragt die ROH-Tabelle ab und umgeht
    // den globalen Eloquent-TenantScope. Ungescopte FK-Validierung erlaubt damit mandantenübergreifende
    // Referenzen (eine Leitung könnte fremde Räume/Ärzte/Bewohner referenzieren). Jede `exists:`-Regel
    // auf tenant-eigene Tabellen MUSS über diesen Helper laufen. Ausnahme: echte globale Referenzdaten
    // ohne tenant_id (z. B. icd_codes, roles) — die bleiben bei der String-Regel.
    protected function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', app(CurrentTenant::class)->id());
    }
}
