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
    ->withMiddleware(function (Middleware $middleware): void {
        // La demo es pública, pero ninguna acción escribe en la base de datos.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Se conservan las respuestas de error estándar de Laravel.
    })
    ->create();
