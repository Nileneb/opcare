<?php

use App\Domains\Capture\Providers\CaptureServiceProvider;
use App\Domains\Speech\Providers\SpeechServiceProvider;
use App\Domains\Vision\Providers\VisionServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    SpeechServiceProvider::class,
    CaptureServiceProvider::class,
    VisionServiceProvider::class,
];
