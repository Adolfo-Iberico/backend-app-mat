<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Gestiona la autenticación, los perfiles, los tokens y la verificación de correo.
 */
class AuthController extends Controller
{
    /**
     * Registro tradicional (email + password)
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => __('email_already_registered'),
            ], 422);
        }

        $roleName = $validated['role'] ?? Role::STUDENT;
        if (! in_array($roleName, [Role::STUDENT, Role::TEACHER], true)) {
            return response()->json([
                'message' => __('invalid_role'),
            ], 422);
        }

        $user = User::create([
            'id' => Str::uuid(),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'full_name' => $validated['full_name'],
            'role' => $roleName,
            'is_active' => true,
            'provider' => 'email',
        ]);

        if ($roleName === Role::STUDENT) {
            UserProfile::create([
                'user_id' => $user->id,
                'academic_level' => $validated['academic_level'] ?? 'basic',
            ]);
        } elseif ($roleName === Role::TEACHER) {
            UserProfile::create([
                'user_id' => $user->id,
                'department' => $validated['department'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
            ]);
        }

        $platform = $this->resolvePlatform($request);
        $token = $user->createToken('auth_token', [$platform])->plainTextToken;

        return response()->json([
            'message' => __('register_success'),
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login tradicional (email + password)
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => __('invalid_credentials'),
            ], 401);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => __('invalid_credentials'),
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => __('user_inactive'),
            ], 403);
        }

        $user->update(['last_login' => now()]);

        $platform = $this->resolvePlatform($request);
        $token = $user->createToken('auth_token', [$platform])->plainTextToken;

        return response()->json([
            'message' => __('login_success'),
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Obtener perfil del usuario autenticado
     */
    public function profile()
    {
        $user = Auth::user();
        $user->load(['studentProfile', 'teacherProfile']);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Actualizar perfil
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'grade' => 'nullable|string|max:50',
            'profile_image' => 'nullable|string|url',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => __('profile_updated'),
            'user' => $user,
        ]);
    }

    /**
     * Cambiar contraseña — invalida TODOS los tokens excepto el actual
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if ($user->isGoogleUser()) {
            return response()->json([
                'message' => __('google_user_cannot_change_password'),
            ], 400);
        }

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => __('current_password_incorrect'),
            ], 400);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => __('password_changed_sessions_revoked'),
        ]);
    }

    /**
     * Cerrar sesión — elimina el token actual
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('logout_success'),
        ]);
    }

    /**
     * Cerrar TODAS las sesiones de una plataforma específica
     */
    public function logoutPlatform(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'required|in:web,android,ios',
        ]);

        $platform = $validated['platform'];
        $deletedCount = $request->user()->tokens()
            ->where('abilities', 'like', '%"'.$platform.'"%')
            ->delete();

        return response()->json([
            'message' => __('platform_sessions_closed', ['platform' => $platform, 'count' => $deletedCount]),
        ]);
    }

    /**
     * Cerrar TODAS las sesiones excepto la actual
     */
    public function logoutAll(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $deletedCount = $request->user()->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return response()->json([
            'message' => __('all_other_sessions_closed', ['count' => $deletedCount]),
        ]);
    }

    /**
     * Listar sesiones activas (dispositivos)
     */
    public function devices(Request $request)
    {
        $tokens = $request->user()->tokens()->get();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $devices = $tokens->map(function ($token) use ($currentTokenId) {
            $abilities = $token->abilities;
            $platform = 'unknown';
            foreach (['web', 'android', 'ios'] as $p) {
                if (in_array($p, $abilities)) {
                    $platform = $p;
                    break;
                }
            }

            return [
                'id' => $token->id,
                'platform' => $platform,
                'name' => $token->name,
                'is_current' => $token->id === $currentTokenId,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
            ];
        });

        return response()->json([
            'devices' => $devices,
        ]);
    }

    /**
     * Refrescar token (renovar expiración)
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $newToken = $user->createToken(
            $currentToken->name,
            $currentToken->abilities
        )->plainTextToken;

        $currentToken->delete();

        return response()->json([
            'access_token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Vincular cuenta de Google a un usuario existente
     */
    public function connectGoogle(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $user = Auth::user();

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);

            $existingUser = User::where('google_id', $googleUser->id)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => __('google_account_linked_to_other'),
                ], 409);
            }

            $user->update([
                'google_id' => $googleUser->id,
                'google_token' => $googleUser->token,
                'provider' => 'google',
                'profile_image' => $user->profile_image ?: $googleUser->avatar,
            ]);

            return response()->json([
                'message' => 'Cuenta de Google vinculada exitosamente',
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Error al vincular cuenta de Google',
            ], 500);
        }
    }

    /**
     * Desvincular cuenta de Google
     */
    public function disconnectGoogle()
    {
        $user = Auth::user();

        // Only allow disconnect if user has verified email (set their own password)
        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'Debes crear una contraseña antes de desvincular Google. Usa "Cambiar contraseña".',
            ], 400);
        }

        $user->update([
            'google_id' => null,
            'google_token' => null,
            'provider' => 'email',
        ]);

        return response()->json([
            'message' => 'Cuenta de Google desvinculada exitosamente',
        ]);
    }

    /**
     * Enviar código de verificación de email
     */
    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Tu correo ya está verificado']);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('email_verify_'.$user->id, $code, 600);

        try {
            Mail::raw("Tu código de verificación es: {$code}\n\nEste código expira en 10 minutos.", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verifica tu correo - Aulamate');
            });
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json(['message' => 'Código de verificación enviado']);
    }

    /**
     * Verificar email con código
     */
    public function verifyEmail(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $storedCode = Cache::get('email_verify_'.$user->id);

        if (! $storedCode || $storedCode !== $validated['code']) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        $user->update(['email_verified_at' => now()]);
        Cache::forget('email_verify_'.$user->id);

        return response()->json(['message' => 'Correo verificado exitosamente']);
    }

    // ========== PRIVATE ==========

    private function resolvePlatform(Request $request): string
    {
        $header = $request->header('X-Platform');
        if ($header && in_array(strtolower($header), ['web', 'android', 'ios'])) {
            return strtolower($header);
        }

        $ua = strtolower($request->userAgent() ?? '');
        if (str_contains($ua, 'android')) {
            return 'android';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            return 'ios';
        }

        return 'web';
    }
}
