<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(\App\Support\Archive::class);
        $this->app->scoped(\App\Support\LiveScoring\SnapshotRepository::class);
        $this->app->scoped(\App\Support\PlayerProjections::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
