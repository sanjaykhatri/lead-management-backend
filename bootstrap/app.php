<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Replace default CSRF middleware with our custom one that exempts API routes
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Run queue worker every minute
        $schedule->command('queue:work --stop-when-empty --tries=3 --timeout=60')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
        
        // Also run queue:work as a daemon (runs continuously until stopped)
        // Uncomment this if you want a persistent worker instead of per-minute jobs
        // $schedule->command('queue:work --daemon --tries=3 --timeout=60')
        //     ->everyMinute()
        //     ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
