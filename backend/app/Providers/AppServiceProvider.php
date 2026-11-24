<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Eager loading wajib di luar produksi agar N+1 cepat ketahuan.
        Model::preventLazyLoading(! $this->app->isProduction());

        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');
    }
}
