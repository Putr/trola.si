<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Set by plain JS on the station page before it auto-reloads, so it must stay unencrypted.
        $middleware->encryptCookies(except: ['autoreload']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
