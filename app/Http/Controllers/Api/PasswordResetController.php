<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Envía enlaces de restablecimiento de contraseña y aplica tokens válidos.
 */
class PasswordResetController extends Controller
{
    /** Envía un enlace de restablecimiento al correo registrado indicado. */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __('password_reset_link_sent'),
            ]);
        }

        return response()->json([
            'message' => __('password_reset_link_error'),
        ], 400);
    }

    /** Cambia la contraseña mediante el correo, token y nueva contraseña proporcionados. */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __('password_reset_success'),
            ]);
        }

        return response()->json([
            'message' => __('password_token_invalid_or_expired'),
        ], 400);
    }
}
