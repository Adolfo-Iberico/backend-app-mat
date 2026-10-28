<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Cuenta autenticable con rol, perfiles y relaciones académicas del usuario. */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'password',
        'full_name',
        'dni',
        'role',
        'is_active',
        'last_login',
        'profile_image',
        'grade',
        'salon_id',
        'google_id',
        'google_token',
        'provider',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
    ];

    protected $appends = ['institution', 'role'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // ========== RELACIONES ==========
    /** Institución única configurada para toda la plataforma. */
    public function getInstitutionAttribute(): ?string
    {
        return InstitutionConfig::canonicalName();
    }

    /** Devuelve el formato de rol que ya consume la API, sin una tabla aparte. */
    public function getRoleAttribute(): ?object
    {
        $role = $this->attributes['role'] ?? null;
        return $role === null ? null : (object) ['name' => $role];
    }

    public function roleName(): ?string
    {
        return $this->attributes['role'] ?? null;
    }

    /** Perfil de estudiante, si la cuenta lo tiene. */
    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    /** Perfil docente, si la cuenta lo tiene. */
    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /** Salón asignado al usuario. */
    public function salon()
    {
        return $this->belongsTo(Salon::class, 'salon_id');
    }

    /** Lecciones impartidas por el usuario como docente. */
    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    /** Evaluaciones creadas por el usuario como docente. */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'teacher_id');
    }

    /** Registros del progreso del usuario en lecciones. */
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** Resultados de evaluaciones del usuario. */
    public function evaluationResults()
    {
        return $this->hasMany(EvaluationResult::class);
    }

    /** Respuestas enviadas por el usuario a preguntas. */
    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    /** Notificaciones destinadas al usuario. */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /** Logros del usuario, con fecha de desbloqueo en la tabla pivote. */
    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /** Eventos académicos del usuario. */
    public function academicEvents()
    {
        return $this->hasMany(AcademicEvent::class);
    }

    /** Suscripciones web push del usuario. */
    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    // ========== MÉTODOS DE AYUDA ==========
    /** Indica si el rol actual es administrador. */
    public function isAdmin()
    {
        return $this->roleName() === Role::ADMIN;
    }

    /** Indica si el rol permite actuar como docente, incluidos coordinador y director. */
    public function isTeacher()
    {
        // Jerarquía: coordinador y director también actúan como docentes
        return in_array($this->roleName(), Role::TEACHING_ROLES, true);
    }

    /** Indica si el rol actual es director. */
    public function isDirector()
    {
        return $this->roleName() === Role::DIRECTOR;
    }

    /** Indica si el rol actual es coordinador. */
    public function isCoordinator()
    {
        return $this->roleName() === Role::COORDINATOR;
    }

    /** Indica si el usuario tiene alcance de docente o coordinador. */
    public function isScopedTeacher()
    {
        // Docente/coordinador ven solo lo suyo; admin y director ven todo (global)
        return in_array($this->roleName(), [Role::TEACHER, Role::COORDINATOR], true);
    }

    /** Indica si el rol actual es estudiante. */
    public function isStudent()
    {
        return $this->roleName() === Role::STUDENT;
    }

    /** Comprueba si el rol coincide con el nombre indicado. */
    public function hasRole($roleName)
    {
        return $this->roleName() === $roleName;
    }

    /** Comprueba si el rol está incluido en la lista proporcionada. */
    public function hasAnyRole($roles)
    {
        return in_array($this->roleName(), $roles, true);
    }

    /** Indica si la cuenta usa Google como proveedor de autenticación. */
    public function isGoogleUser()
    {
        return $this->provider === 'google';
    }
} 