<?php

use App\Domains\Speech\Providers\SpeechServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    SpeechServiceProvider::class,
];
