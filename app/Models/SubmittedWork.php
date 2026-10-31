<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trabajo enviado por un estudiante para una lección o evaluación.
 *
 * Desde la consolidación del módulo, los trabajos de origen examen usan
 * evaluation_id con work_type='exam' (el ID coincide con el de la evaluación
 * canónica type='exam').
 */
class SubmittedWork extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'student_id',
        'lesson_id',
        'evaluation_id',
        'work_type',
        'title',
        'description',
        'status',
        'score',
        'max_score',
        'teacher_feedback',
        'attachments',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'score' => 'integer',
        'max_score' => 'integer',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    /** Campos derivados que consumen los paneles de trabajos del frontend. */
    protected $appends = ['student_name', 'teacher_name', 'area', 'feedback'];

    // ========== RELACIONES ==========

    /** Estudiante que envió el trabajo. */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** Lección relacionada con el trabajo, si corresponde. */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /** Evaluación (o examen, type='exam') relacionada con el trabajo, si corresponde. */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    // ========== ACCESORES ==========

    /** Nombre completo del estudiante para los listados del panel. */
    public function getStudentNameAttribute(): ?string
    {
        return $this->student?->full_name;
    }

    /** Docente propietario del contenido asociado al trabajo. */
    public function getTeacherNameAttribute(): ?string
    {
        return $this->evaluation?->teacher?->full_name
            ?? $this->lesson?->teacher?->full_name;
    }

    /** Área temática normalizada (clave slug) derivada de la unidad. */
    public function getAreaAttribute(): ?string
    {
        $unit = $this->evaluation?->unit
            ?? $this->lesson?->unit
            ?? $this->evaluation?->lesson?->unit;

        if ($unit === null || $unit === '') {
            return null;
        }

        return Str::slug($unit) ?: null;
    }

    /** Alias de compatibilidad del feedback docente. */
    public function getFeedbackAttribute(): ?string
    {
        return $this->teacher_feedback;
    }

    // ========== SCOPES ==========

    /** Limita la consulta a los trabajos de un estudiante. */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /** Filtra los trabajos con estado pendiente. */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Filtra los trabajos enviados. */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /** Filtra los trabajos calificados. */
    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }
}
