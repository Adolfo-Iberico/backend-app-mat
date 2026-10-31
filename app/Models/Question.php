<?php

namespace App\Models;

use App\Support\EvaluationAnswerFormatter;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pregunta de una evaluación con las respuestas enviadas por estudiantes. */
class Question extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'evaluation_id',
        'type',
        'question_text',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'points' => 'integer',
        'order' => 'integer',
    ];

    protected $appends = ['correct_answer_display'];

    public function getCorrectAnswerDisplayAttribute(): string
    {
        return EvaluationAnswerFormatter::correctAnswer($this);
    }

    // ========== CONSTANTES ==========
    const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    const TYPE_FILL_BLANK = 'fill_blank';

    const TYPE_DRAG_DROP = 'drag_drop';

    const TYPE_FORMULA = 'formula';

    const TYPE_TRUE_FALSE = 'true_false';

    const TYPE_OPEN_ENDED = 'open_ended';

    // ========== RELACIONES ==========
    /** Evaluación a la que pertenece la pregunta. */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** Respuestas enviadas por estudiantes a esta pregunta. */
    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
