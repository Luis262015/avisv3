<?php

namespace App\Providers;

use App\Models\Payable;
use App\Observers\PayableObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Payable::observe(PayableObserver::class);
    }
}
