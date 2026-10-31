<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Evaluación creada por un docente, opcionalmente ligada a una lección. */
class Evaluation extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

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

    protected $casts = [
        'time_limit' => 'integer',
        'due_date' => 'datetime',
        'is_published' => 'boolean',
        'auto_correct' => 'boolean',
        'randomize_questions' => 'boolean',
        'max_attempts' => 'integer',
        'published_at' => 'datetime',
        'total_questions' => 'integer',
        'total_points' => 'integer',
    ];

    // ========== CONSTANTES ==========
    const TYPE_EXAM = 'exam';

    const TYPE_QUIZ = 'quiz';

    const TYPE_HOMEWORK = 'homework';

    const TYPE_PRACTICE = 'practice';

    const DIFFICULTY_BASIC = 'basic';

    const DIFFICULTY_INTERMEDIATE = 'intermediate';

    const DIFFICULTY_ADVANCED = 'advanced';

    // ========== RELACIONES ==========
    /** Docente que creó la evaluación. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Lección asociada a la evaluación, si existe. */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /** Preguntas incluidas en la evaluación. */
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /** Resultados obtenidos por los estudiantes. */
    public function results()
    {
        return $this->hasMany(EvaluationResult::class);
    }

    // ========== SCOPES ==========
    /** Filtra las evaluaciones publicadas. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** Filtra las evaluaciones por tipo. */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /** Filtra las evaluaciones por dificultad. */
    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }
}
