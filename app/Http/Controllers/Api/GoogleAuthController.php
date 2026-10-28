<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;

/**
 * Autentica usuarios mediante OAuth de Google o la verificación móvil de ID tokens.
 */
class GoogleAuthController extends Controller
{
    /** Redirige el navegador a Google para seleccionar una cuenta e iniciar sesión. */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /** Procesa el retorno de OAuth y redirige con un código de autenticación temporal. */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($user) {
                $updates = [];
                if (!$user->google_id) {
                    $updates = [
                        'google_id' => $googleUser->id,
                        'google_token' => $googleUser->token,
                        'provider' => 'google'
                    ];
                }
                if (!$user->profile_image && $googleUser->avatar) {
                    $updates['profile_image'] = $googleUser->avatar;
                }
                if ($updates) {
                    $user->update($updates);
                }

                if (!$user->is_active) {
                    return $this->redirectToFrontendLogin(['error' => 'inactive']);
                }

                $user->update(['last_login' => now()]);
            } else {
                $user = User::create([
                    'id' => Str::uuid(),
                    'email' => $googleUser->email,
                    'full_name' => $googleUser->name,
                    'profile_image' => $googleUser->avatar,
                    'google_id' => $googleUser->id,
                    'google_token' => $googleUser->token,
                    'provider' => 'google',
                    'role' => Role::STUDENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(24))
                ]);

                StudentProfile::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'academic_level' => 'basic'
                ]);
            }

            $token = $user->createToken('auth_token', ['web'])->plainTextToken;

            // Use a short-lived auth code instead of the raw token in URL
            $code = Str::random(32);
            Cache::put('google_auth_code:' . $code, $token, now()->addMinutes(5));
            Log::info('google_login_succeeded', [
                'user_id' => $user->id,
                'method' => 'oauth_callback',
                'platform' => 'web',
            ]);

            return $this->redirectToFrontendLogin(['auth_code' => $code]);
        } catch (\Exception $e) {
            report($e);
            return $this->redirectToFrontendLogin(['error' => 'auth_failed']);
        }
    }

    private function redirectToFrontendLogin(array $query)
    {
        $frontendUrl = rtrim((string) config('services.google.frontend_url'), '/');

        return redirect($frontendUrl . '/login?' . http_build_query($query));
    }

    /**
     * Login con Google ID token — para app móvil
     */
    public function loginWithGoogleToken(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        try {
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->access_token);
            if (!$payload) {
                return response()->json(['message' => __('google_invalid_token')], 401);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $avatar = $payload['picture'] ?? null;

            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                $updates = [];
                if (!$user->google_id) {
                    $updates = [
                        'google_id' => $googleId,
                        'provider' => 'google'
                    ];
                }
                if (!$user->profile_image && $avatar) {
                    $updates['profile_image'] = $avatar;
                }
                if ($updates) {
                    $user->update($updates);
                }
                if (!$user->is_active) {
                    return response()->json(['message' => __('google_user_inactive_contact_admin')], 403);
                }
                $user->update(['last_login' => now()]);
            } else {
                $user = User::create([
                    'id' => Str::uuid(),
                    'email' => $email,
                    'full_name' => $name,
                    'profile_image' => $avatar,
                    'google_id' => $googleId,
                    'provider' => 'google',
                    'role' => Role::STUDENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(24))
                ]);

                StudentProfile::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'academic_level' => 'basic'
                ]);
            }

            // Detectar plataforma desde header
            $platform = $this->resolvePlatform($request);
            $token = $user->createToken('auth_token', [$platform])->plainTextToken;
            Log::info('google_login_succeeded', [
                'user_id' => $user->id,
                'method' => 'id_token',
                'platform' => $platform,
            ]);

            return response()->json([
                'message' => __('google_auth_success'),
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => __('google_auth_error')
            ], 500);
        }
    }

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

    /**
     * Exchange a short-lived auth code for the actual token (web callback flow)
     */
    public function exchangeCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $cacheKey = 'google_auth_code:' . $request->code;
        $token = Cache::pull($cacheKey);

        if (!$token) {
            return response()->json(['message' => __('code_invalid_or_expired')], 401);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}
