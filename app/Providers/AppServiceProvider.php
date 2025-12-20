<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\MatchModel;
use App\Observers\MatchObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Подключаем observer для матчей
        MatchModel::observe(MatchObserver::class);
    }
}
