<?php

use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CacheResponse;
use App\Http\Middleware\Cors;
use App\Http\Middleware\GlobalRateLimit;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'cors' => Cors::class,
            'auth.active' => Authenticate::class,
            'rate.limit' => ApiRateLimit::class,
            'cache.api' => CacheResponse::class,
        ]);
        $middleware->prepend([
            Cors::class,
            SecurityHeaders::class,
            GlobalRateLimit::class,
            SetRequestLocale::class,
        ]);
        $middleware->redirectGuestsTo(fn ($request) => $request->expectsJson() ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($e instanceof AuthenticationException ||
                    $e instanceof ValidationException) {
                    return null;
                }

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'message' => match ($status) {
                        404 => 'Recurso no encontrado',
                        403 => 'No tienes permiso para realizar esta acción',
                        401 => 'No autenticado',
                        422 => 'Datos de entrada inválidos',
                        429 => 'Demasiadas solicitudes. Intenta más tarde.',
                        default => 'Error interno del servidor',
                    },
                ], $status);
            }
        });
    })->create();
