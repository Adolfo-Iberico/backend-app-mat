<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Resultado de un estudiante en una evaluación, con sus respuestas registradas. */
class EvaluationResult extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'evaluation_id',
        'score',
        'max_score',
        'correct_answers',
        'total_questions',
        'time_taken',
        'status',
        'started_at',
        'completed_at',
        'attempt_number',
        'tab_switch_count',
        'total_points',
        'answers',
        'cheat_log',
    ];

    protected $casts = [
        'score' => 'float',
        'max_score' => 'float',
        'correct_answers' => 'integer',
        'total_questions' => 'integer',
        'time_taken' => 'integer',
        'attempt_number' => 'integer',
        'tab_switch_count' => 'integer',
        'total_points' => 'integer',
        'answers' => 'array',
        'cheat_log' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ========== CONSTANTES ==========
    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CHEATING_DETECTED = 'cheating_detected';

    // ========== RELACIONES ==========
    /** Estudiante que obtuvo el resultado. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Evaluación a la que corresponde el resultado. */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** Respuestas asociadas al intento de evaluación. */
    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    // ========== SCOPES ==========
    /** Filtra los resultados completados. */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /** Filtra los resultados pendientes. */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
