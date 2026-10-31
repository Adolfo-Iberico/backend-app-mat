<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica a las solicitudes API el límite configurado por usuario o dirección IP.
 */
class ApiRateLimit
{
    /** Aplica el límite de API y agrega encabezados de uso a la respuesta. */
    public function handle(Request $request, Closure $next): Response
    {
        // Alineado con GlobalRateLimit. Con 60 req/min el límite global se
        // alcanzaba durante la navegación normal (3-6 llamadas por página) y
        // devolvía 429. Ver config/ratelimit.php.
        $maxAttempts = (int) config('ratelimit.api.max_attempts', 120);
        $decayMinutes = (int) config('ratelimit.api.decay_minutes', 1);
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
            return 'api_token_'.$userId;
        }

        return 'api_ip_'.$ip;
    }
}
