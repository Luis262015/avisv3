<?php

namespace App\Providers;

use App\Models\PayablePayment;
use App\Observers\PayablePaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        PayablePayment::observe(PayablePaymentObserver::class);
    }
}
