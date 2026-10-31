<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Salón académico con período, coordinador y estudiantes asignados. */
class Salon extends Model
{
    use HasUuids;

    protected $table = 'salones';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'grade',
        'section',
        'academic_period_id',
        'coordinator_id',
    ];

    /** Período académico al que pertenece el salón. */
    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /** Usuario coordinador del salón. */
    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    /** Estudiantes asignados al salón. */
    public function students()
    {
        return $this->hasMany(User::class, 'salon_id');
    }

    /** Nombre visible formado por el grado y la sección. */
    public function getDisplayNameAttribute()
    {
        return trim("{$this->grade} \"{$this->section}\"");
    }
}
