<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(\App\Support\Archive::class);
    }

    public function boot(): void
    {
        try {
            if (Schema::hasTable('seasons')) {
                Log::info('ECFHL relational counts', [
                    'seasons' => DB::table('seasons')->count(),
                    'franchises' => DB::table('franchises')->count(),
                    'team_seasons' => DB::table('team_seasons')->count(),
                    'players' => DB::table('players')->count(),
                    'draft_picks' => DB::table('draft_picks')->count(),
                    'trades' => DB::table('trades')->count(),
                    'trade_assets' => DB::table('trade_assets')->count(),
                    'awards' => DB::table('awards')->count(),
                    'rules' => DB::table('rules')->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to log ECFHL relational counts', ['error' => $e->getMessage()]);
        }
    }
}
