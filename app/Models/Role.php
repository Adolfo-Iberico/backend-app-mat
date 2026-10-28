<?php

namespace App\Models;

/** Valores de rol permitidos por el enum users.role. */
final class Role
{
    public const ADMIN = 'admin';
    public const TEACHER = 'teacher';
    public const STUDENT = 'student';
    public const PARENT = 'parent';
    public const DIRECTOR = 'director';
    public const COORDINATOR = 'coordinador';

    public const TEACHING_ROLES = [
        self::TEACHER,
        self::COORDINATOR,
        self::DIRECTOR,
    ];
}
