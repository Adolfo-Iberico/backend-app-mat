<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa un fragmento de contenido dentro de una lección (Micro-lección).
 */
class LessonSegment extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'lesson_id',
        'title',
        'type',
        'content',
        'url',
        'order',
        'duration_seconds',
    ];

    protected $casts = [
        'order' => 'integer',
        'duration_seconds' => 'integer',
    ];

    /**
     * La lección a la que pertenece este segmento.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
