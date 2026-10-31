<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los campos de correo y contraseña para iniciar sesión con credenciales.
 */
class LoginRequest extends FormRequest
{
    /** Autoriza la solicitud para que se apliquen sus reglas de validación. */
    public function authorize(): bool
    {
        return true;
    }

    /** Define las reglas de validación para el correo y la contraseña requeridos. */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ];
    }

    /** Devuelve los mensajes traducidos para errores de validación del inicio de sesión. */
    public function messages(): array
    {
        return [
            'email.required' => __('validation_email_required'),
            'email.email' => __('validation_email_invalid'),
            'password.required' => __('validation_password_required'),
            'password.min' => __('validation_password_min'),
        ];
    }
}
