<?php

namespace App\Models;

/**
 * Examen: evaluación de type='exam' supervisada con anti-cheat.
 *
 * Desde la consolidación del módulo (Fase 2) los exámenes viven en la tabla
 * canónica `evaluations`. Este modelo es la vista de compatibilidad del flujo
 * de exámenes: un global scope filtra por type='exam' y el alias is_active
 * mapea a is_published (en el flujo antiguo ambos flags se alternaban juntos,
 * por lo que un solo flag gobierna la visibilidad para estudiantes).
 *
 * Se preservan los IDs originales: cualquier referencia externa (payloads del
 * frontend /exams/*, submitted_works, conversaciones) sigue siendo válida.
 */
class Exam extends Evaluation
{
    protected $table = 'evaluations';

    protected $fillable = [
        'id',
        'title',
        'description',
        'teacher_id',
        'lesson_id',
        'unit',
        'type',
        'difficulty',
        'time_limit',
        'due_date',
        'is_published',
        'auto_correct',
        'randomize_questions',
        'max_attempts',
        'published_at',
        'total_questions',
        'total_points',
    ];

    /** El alias is_active se serializa en los payloads del flujo de exámenes. */
    protected $appends = ['is_active'];

    public const TYPE = parent::TYPE_EXAM;

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('exam', function ($query) {
            $query->where('type', self::TYPE);
        });

        // Todo examen creado desde este modelo nace con su tipo canónico.
        static::creating(function ($exam) {
            $exam->type = self::TYPE;
        });
    }

    /** is_active (legado) === is_published: un solo flag de visibilidad. */
    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->is_published;
    }

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['is_published'] = $value ? 1 : 0;
    }

    /** Intentos de estudiantes en este examen (filas de evaluation_results). */
    public function attempts()
    {
        return $this->hasMany(EvaluationResult::class, 'evaluation_id');
    }

    /**
     * Preguntas del examen. FK explícita: la heredada derivaría "exam_id"
     * del nombre de esta clase y no de la tabla canónica evaluations.
     */
    public function questions()
    {
        return $this->hasMany(Question::class, 'evaluation_id');
    }

    /** Resultados de estudiantes (alias semántico de attempts). */
    public function results()
    {
        return $this->hasMany(EvaluationResult::class, 'evaluation_id');
    }

    /** Filtra los exámenes activos (publicados). */
    public function scopeActive($query)
    {
        return $query->where('is_published', true);
    }

    /** Filtra los exámenes inactivos, que este modelo usa como borradores. */
    public function scopeDraft($query)
    {
        return $query->where('is_published', false);
    }

    /** Filtra los exámenes asignados a un docente. */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
