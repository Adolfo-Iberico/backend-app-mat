<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica el límite global configurado de solicitudes por usuario o dirección IP.
 */
class GlobalRateLimit
{
    /** Aplica el límite global y agrega encabezados de uso a la respuesta. */
    public function handle(Request $request, Closure $next): Response
    {
        // 60 req/min resultaba insuficiente: la app emite entre 3 y 6 llamadas
        // por navegación, con lo que ~10 páginas por minuto agotaban el cupo y
        // el usuario veía errores 429 en uso normal. Ver config/ratelimit.php.
        $maxAttempts = (int) config('ratelimit.global.max_attempts', 120);
        $decayMinutes = (int) config('ratelimit.global.decay_minutes', 1);
        $limiterKey = $this->resolveLimiterKey($request);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($limiterKey);

            return response()->json([
                'message' => 'Demasiadas solicitudes. Intenta de nuevo en '.$retryAfter.' segundos.',
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($limiterKey, $decayMinutes * 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($limiterKey, $maxAttempts));

        return $response;
    }

    private function resolveLimiterKey(Request $request): string
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();

        if ($userId) {
            return 'global_api_token_'.$userId;
        }

        return 'global_api_ip_'.$ip;
    }
}
