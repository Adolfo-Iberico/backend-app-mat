<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

/** Autoriza acciones sobre lecciones según rol, propiedad y publicación. */
class LessonPolicy
{
    /** Permite consultar el listado de lecciones a cualquier usuario autenticado. */
    public function viewAny(User $user)
    {
        return true;
    }

    /** Comprueba si el usuario puede ver la lección concreta. */
    public function view(User $user, Lesson $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $lesson->teacher_id === $user->id) {
            return true;
        }

        if ($user->isStudent() && $lesson->is_published) {
            return true;
        }

        return false;
    }

    /** Permite crear lecciones a administradores y docentes. */
    public function create(User $user)
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    /** Permite actualizar al administrador o al docente propietario. */
    public function update(User $user, Lesson $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $lesson->teacher_id === $user->id;
    }

    /** Permite eliminar al administrador o al docente propietario. */
    public function delete(User $user, Lesson $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $lesson->teacher_id === $user->id;
    }

    /** Aplica las mismas reglas de autorización que la actualización. */
    public function publish(User $user, Lesson $lesson)
    {
        return $this->update($user, $lesson);
    }
}
