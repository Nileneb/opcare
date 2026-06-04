<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;

class HealthInsurance extends BaseModel
{
    protected $fillable = ['tenant_id', 'name', 'ik_nummer'];
}
