<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Set timezone based on authenticated user's profile for each request
        $this->app->booted(function () {
            // This will be handled per-request in middleware if needed
            // For now, we'll use UTC as default and convert in controllers
        });
    }
}

