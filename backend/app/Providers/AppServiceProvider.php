<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Kebijakan kata sandi (PRD 9): minimal 8 karakter dengan huruf besar, huruf kecil, dan angka.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
    }
}
