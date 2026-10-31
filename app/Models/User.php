<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** Cuenta autenticable con rol, perfiles y relaciones acadÃ©micas del usuario. */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'password',
        'full_name',
        'dni',
        'role',
        'role_id',
        'is_active',
        'last_login',
        'profile_image',
        'salon_id',
        'google_id',
        'google_token',
        'provider',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
    ];

    protected $appends = ['institution', 'role', 'grade'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // ========== RELACIONES ==========
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function setRoleAttribute($value): void
    {
        if ($value instanceof Role) {
            $value = $value->name;
        }

        if (is_string($value) && $value !== '') {
            $this->attributes['role_id'] = Role::firstOrCreate(['name' => $value])->id;
        }
    }

    public function getRoleAttribute(): ?Role
    {
        return $this->getRelationValue('role');
    }

    public function getInstitutionAttribute(): ?string
    {
        return InstitutionConfig::canonicalName();
    }

    public function roleName(): ?string
    {
        return $this->role?->name;
    }

    public function scopeForRole(Builder $query, string $roleName): Builder
    {
        return $query->whereHas('role', fn (Builder $roles) => $roles->where('name', $roleName));
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class, 'user_id');
    }

    public function salon()
    {
        return $this->belongsTo(Salon::class, 'salon_id');
    }

    public function getGradeAttribute(): ?string
    {
        return $this->salon?->grade;
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'teacher_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function evaluationResults()
    {
        return $this->hasMany(EvaluationResult::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function academicEvents()
    {
        return $this->hasMany(AcademicEvent::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class)->where('platform', 'web');
    }

    // ========== MÃ‰TODOS DE AYUDA ==========
    public function isAdmin()
    {
        return $this->roleName() === Role::ADMIN;
    }

    public function isTeacher()
    {
        return in_array($this->roleName(), Role::TEACHING_ROLES, true);
    }

    public function isDirector()
    {
        return $this->roleName() === Role::DIRECTOR;
    }

    public function isCoordinator()
    {
        return $this->roleName() === Role::COORDINATOR;
    }

    public function isScopedTeacher()
    {
        return in_array($this->roleName(), [Role::TEACHER, Role::COORDINATOR], true);
    }

    public function isStudent()
    {
        return $this->roleName() === Role::STUDENT;
    }

    public function hasRole($roleName)
    {
        return $this->roleName() === $roleName;
    }

    public function hasAnyRole($roles)
    {
        return in_array($this->roleName(), $roles, true);
    }

    public function isGoogleUser()
    {
        return $this->provider === 'google';
    }
}
