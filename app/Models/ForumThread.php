<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vista de compatibilidad para hilos de foro.
 * Ahora vive en la tabla canónica `threads` con type='forum'.
 */
class ForumThread extends Thread
{
    protected $table = 'threads';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected static function booted(): void
    {
        static::addGlobalScope('forum', function ($query) {
            $query->where('type', 'forum');
        });

        static::creating(function ($thread) {
            $thread->type = 'forum';
        });
    }

    /** Alias para el docente responsable. */
    public function teacher()
    {
        return $this->creator();
    }

    /** Alias para el autor del hilo. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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
