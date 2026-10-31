<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Modelo que representa los roles del sistema en la base de datos. */
class Role extends Model
{
    protected $fillable = ['name', 'description'];

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
