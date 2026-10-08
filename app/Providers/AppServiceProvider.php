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
        $this->app->scoped(\App\Support\WebPush::class);
        $this->app->scoped(\App\Support\CollectorSchedule::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
            if ($key = \App\Support\CollectorStatus::JOBS[$event->command] ?? null) \App\Support\CollectorStatus::start($key);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandFinished::class, function ($event) {
            if ($key = \App\Support\CollectorStatus::JOBS[$event->command] ?? null) \App\Support\CollectorStatus::finish($key, $event->exitCode);
        });
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
