<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos de cuenta y los campos específicos del rol para el registro.
 */
class RegisterRequest extends FormRequest
{
    /** Autoriza la solicitud para que se apliquen sus reglas de validación. */
    public function authorize(): bool
    {
        return true;
    }

    /** Define las reglas de registro de cuenta y los campos específicos del rol. */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:student',
            'academic_level' => 'required_if:role,student|in:basic,intermediate,advanced',
            'grade' => 'nullable|string|max:50',
            'department' => 'required_if:role,teacher|string|max:255',
            'specialization' => 'required_if:role,teacher|string|max:255',
        ];
    }

    /** Devuelve los mensajes traducidos para errores de validación del registro. */
    public function messages(): array
    {
        return [
            'full_name.required' => __('validation_full_name_required'),
            'email.required' => __('validation_email_required'),
            'email.unique' => __('email_already_registered'),
            'password.required' => __('validation_password_required'),
            'password.min' => __('validation_password_min'),
            'password.confirmed' => __('validation_password_confirmed'),
            'role.in' => __('validation_role_invalid'),
        ];
    }
}
