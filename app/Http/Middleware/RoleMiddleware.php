<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe rutas a los roles solicitados y respeta la jerarquía docente-coordinador-director.
 */
class RoleMiddleware
{
    /** Comprueba la autenticación y el rol antes de continuar la solicitud. */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        $userRole = $user->roleName();

        // Jerarquía: director > coordinador > docente
        $hierarchyMap = [
            Role::TEACHER => [Role::TEACHER, Role::COORDINATOR, Role::DIRECTOR],
            Role::COORDINATOR => [Role::COORDINATOR, Role::DIRECTOR],
        ];

        foreach ($roles as $required) {
            $allowed = $hierarchyMap[$required] ?? [$required];
            if ($userRole && in_array($userRole, $allowed, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'No tienes permisos para acceder a este recurso',
        ], 403);
    }
}
