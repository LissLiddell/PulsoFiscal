<?php

namespace App\Providers;

use App\Contracts\Certifier;
use App\Services\DemoCertifier;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Certifier::class, DemoCertifier::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
