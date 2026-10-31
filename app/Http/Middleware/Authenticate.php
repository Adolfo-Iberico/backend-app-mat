<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Autentica solicitudes e impide que cuentas desactivadas sigan usando sus tokens.
 */
class Authenticate extends Middleware
{
    /** Indica el destino de redirección para solicitudes no JSON sin autenticar. */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return '/';
    }

    /**
     * Tras autenticar, verifica que la cuenta siga activa (is_active).
     * Si el usuario fue desactivado, revoca su token actual y deniega el acceso.
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        $user = $request->user();
        if ($user && ! $user->is_active) {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
            $this->unauthenticated($request, $guards);
        }
    }
}
