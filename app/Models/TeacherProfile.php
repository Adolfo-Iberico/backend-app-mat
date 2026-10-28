<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** Perfil profesional de un usuario docente y sus recursos asignados. */
class TeacherProfile extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'department',
        'specialization',
        'years_experience'
    ];

    protected $casts = [
        'years_experience' => 'integer'
    ];

    // ========== RELACIONES ==========
    /** Usuario al que corresponde el perfil docente. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Lecciones asignadas al docente. */
    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id', 'user_id');
    }

    /** Evaluaciones creadas por el docente. */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'teacher_id', 'user_id');
    } 
}