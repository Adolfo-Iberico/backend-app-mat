<?php

namespace App\Models;

use App\Support\EvaluationAnswerFormatter;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Respuesta de un usuario a una pregunta dentro de un resultado de evaluación. */
class StudentAnswer extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'evaluation_result_id',
        'question_id',
        'answer',
        'is_correct',
        'points_earned',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'float',
    ];

    protected $appends = ['answer_display'];

    public function getAnswerDisplayAttribute(): string
    {
        return $this->relationLoaded('question') && $this->question
            ? EvaluationAnswerFormatter::submittedAnswer($this->question, (string) $this->answer)
            : (string) $this->answer;
    }

    // ========== RELACIONES ==========
    /** Usuario que envió la respuesta. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Resultado de evaluación que contiene la respuesta. */
    public function evaluationResult()
    {
        return $this->belongsTo(EvaluationResult::class);
    }

    /** Pregunta respondida. */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
