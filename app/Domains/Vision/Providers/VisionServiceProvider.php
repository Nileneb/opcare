<?php

namespace App\Domains\Vision\Providers;

use App\Domains\Vision\Contracts\VisionClient;
use App\Domains\Vision\Services\HttpVisionClient;
use App\Domains\Vision\Testing\FakeVisionClient;
use Illuminate\Support\ServiceProvider;

class VisionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            VisionClient::class,
            config('vision.fake') ? FakeVisionClient::class : HttpVisionClient::class,
        );
    }
}
