<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Estado y avance de un usuario en una lección. */
class LessonProgress extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'lesson_id',
        'progress',
        'status',
        'completed_at',
        'time_spent',
        'last_position',
    ];

    protected $casts = [
        'progress' => 'integer',
        'time_spent' => 'integer',
        'last_position' => 'integer',
        'completed_at' => 'datetime',
    ];

    // ========== CONSTANTES ==========
    const STATUS_NOT_STARTED = 'not_started';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_COMPLETED = 'completed';

    // ========== RELACIONES ==========
    /** Usuario cuyo progreso registra este modelo. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Lección cuyo progreso se registra. */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    // ========== SCOPES ==========
    /** Filtra el progreso aún no iniciado. */
    public function scopeNotStarted($query)
    {
        return $query->where('status', self::STATUS_NOT_STARTED);
    }

    /** Filtra el progreso actualmente en curso. */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /** Filtra el progreso completado. */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
