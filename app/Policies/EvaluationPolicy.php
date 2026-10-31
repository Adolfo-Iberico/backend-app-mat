<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

/** Autoriza acciones sobre evaluaciones según rol, propiedad y publicación. */
class EvaluationPolicy
{
    /** Permite consultar el listado de evaluaciones a cualquier usuario autenticado. */
    public function viewAny(User $user)
    {
        return true;
    }

    /** Comprueba si el usuario puede ver la evaluación concreta. */
    public function view(User $user, Evaluation $evaluation)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $evaluation->teacher_id === $user->id) {
            return true;
        }

        if ($user->isStudent() && $evaluation->is_published) {
            return true;
        }

        return false;
    }

    /** Permite crear evaluaciones a administradores y docentes. */
    public function create(User $user)
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    /** Permite actualizar al administrador o al docente propietario. */
    public function update(User $user, Evaluation $evaluation)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $evaluation->teacher_id === $user->id;
    }

    /** Permite eliminar al administrador o al docente propietario. */
    public function delete(User $user, Evaluation $evaluation)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $evaluation->teacher_id === $user->id;
    }

    /** Aplica las mismas reglas de autorización que la actualización. */
    public function publish(User $user, Evaluation $evaluation)
    {
        return $this->update($user, $evaluation);
    }

    /** Permite enviar respuestas a estudiantes cuando la evaluación está publicada. */
    public function submit(User $user, Evaluation $evaluation)
    {
        return $user->isStudent() && $evaluation->is_published;
    }
}
