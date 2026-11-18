<?php

use App\Support\ErroresMysql;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Un error de MySQL que no atrapo un servicio se responde con el codigo HTTP que le corresponde
        // (UNIQUE -> 409, FK -> 409/422, CHECK -> 422, regla de la base -> 422...). Ver App\Support\ErroresMysql.
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $t = ErroresMysql::traducir($e);

                return response()->json(['message' => $t['mensaje']], $t['status']);
            }
        });
    })->create();
