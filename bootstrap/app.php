<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/';
        });

        $middleware->alias([
            'is.driver' => \App\Http\Middleware\EnsureUserIsDriver::class,
            'is.guide' => \App\Http\Middleware\EnsureUserIsGuide::class,
            'is.guest' => \App\Http\Middleware\EnsureUserIsGuest::class,
            'is.restaurant' => \App\Http\Middleware\EnsureUserIsRestaurant::class,
            'is.agent' => \App\Http\Middleware\EnsureUserIsAgent::class,
            'is.dmc' => \App\Http\Middleware\EnsureUserIsDmc::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*');
        });
    })->create();
