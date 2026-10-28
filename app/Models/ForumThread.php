<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Hilo del foro vinculado a una lección y a un docente. */
class ForumThread extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'teacher_id',
        'author_id',
        'lesson_id',
        'title',
        'body',
        'status',
    ];

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    /** Docente responsable del hilo. */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** Usuario que abrió el hilo (docente o estudiante). */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Lección relacionada con el hilo. */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /** Publicaciones del hilo. */
    public function posts()
    {
        return $this->hasMany(ForumPost::class, 'thread_id');
    }

    /** Filtra los hilos con estado abierto. */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    protected function isClosed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === self::STATUS_CLOSED
        );
    }
}
