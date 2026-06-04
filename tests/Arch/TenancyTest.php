<?php

arch('Domänen-Modelle erben von BaseModel (Tenant-Scope) oder sind Referenzdaten')
    ->expect('App\Domains\Masterdata\Models')
    ->toExtend('App\Support\Models\BaseModel')
    ->ignoring('App\Domains\Masterdata\Models\IcdCode');
