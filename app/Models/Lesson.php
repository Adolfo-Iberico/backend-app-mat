<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Lección impartida por un docente dentro de un curso, con progreso y evaluaciones. */
class Lesson extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'content',
        'teacher_id',
        'grade',
        'unit',
        'topic',
        'difficulty',
        'tags',
        'estimated_time',
        'is_published',
        'published_at',
        'resources',
        'order',
        'views_count',
    ];

    protected $casts = [
        'tags' => 'array',
        'resources' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'estimated_time' => 'integer',
        'order' => 'integer',
        'views_count' => 'integer',
    ];

    // ========== CONSTANTES ==========
    const DIFFICULTY_BASIC = 'basic';

    const DIFFICULTY_INTERMEDIATE = 'intermediate';

    const DIFFICULTY_ADVANCED = 'advanced';

    // ========== RELACIONES ==========
    /** Docente responsable de la lección. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Registros de progreso de los estudiantes en la lección. */
    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** Evaluaciones asociadas a la lección. */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    /** Fragmentos de contenido de la lección (Micro-lecciones). */
    public function segments()
    {
        return $this->hasMany(LessonSegment::class)->orderBy('order', 'asc');
    }

    // ========== SCOPES ==========
    /** Filtra las lecciones publicadas. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** Filtra las lecciones por dificultad. */
    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    /** Filtra las lecciones por unidad. */
    public function scopeByUnit($query, $unit)
    {
        return $query->where('unit', $unit);
    }

    /** Filtra las lecciones por tema. */
    public function scopeByTopic($query, $topic)
    {
        return $query->where('topic', $topic);
    }
}
