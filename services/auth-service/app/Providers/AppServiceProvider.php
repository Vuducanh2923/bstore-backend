<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! is_string(config('auth.token_key')) || trim((string) config('auth.token_key')) === '') {
            throw new RuntimeException('AUTH_TOKEN_KEY is required and has no fallback.');
        }
    }
}
