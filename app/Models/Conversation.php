<?php

namespace App\Models;

/**
 * Vista de compatibilidad para conversaciones directas.
 * Ahora vive en la tabla canónica `threads` con type='direct'.
 */
class Conversation extends Thread
{
    protected $table = 'threads';

    protected static function booted(): void
    {
        static::addGlobalScope('direct', function ($query) {
            $query->where('type', 'direct');
        });

        static::creating(function ($thread) {
            $thread->type = 'direct';
        });
    }

    /** Compatibilidad con el nombre de relación 'messages' usado en el frontend/controladores. */
    public function messages()
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    /** Alias para el docente participante. */
    public function teacher()
    {
        return $this->creator();
    }
}
