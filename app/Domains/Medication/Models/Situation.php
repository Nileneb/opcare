<?php

namespace App\Domains\Medication\Models;

use App\Support\Models\BaseModel;

class Situation extends BaseModel
{
    protected $fillable = ['tenant_id', 'name'];
}
