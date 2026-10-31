<?php

namespace App\Models;

/**
 * Vista de compatibilidad para mensajes de conversación.
 * Ahora vive en la tabla canónica `posts`.
 */
class Message extends Post
{
    protected $table = 'posts';

    protected $fillable = [
        'id',
        'thread_id',
        'user_id',
        'body',
        'read_at',
        'conversation_id',
        'sender_id',
    ];

    protected $appends = ['conversation_id', 'sender_id'];

    public function setConversationIdAttribute($value): void
    {
        $this->attributes['thread_id'] = $value;
    }

    public function getConversationIdAttribute(): ?string
    {
        return $this->attributes['thread_id'] ?? null;
    }

    public function setSenderIdAttribute($value): void
    {
        $this->attributes['user_id'] = $value;
    }

    public function getSenderIdAttribute(): ?string
    {
        return $this->attributes['user_id'] ?? null;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('direct', function ($query) {
            $query->join('threads', 'posts.thread_id', '=', 'threads.id')
                ->where('threads.type', 'direct');
        });
    }

    /** Conversación a la que pertenece el mensaje. */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'thread_id');
    }

    /** Usuario que envió el mensaje. */
    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
