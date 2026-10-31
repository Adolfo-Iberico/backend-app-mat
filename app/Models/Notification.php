<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Notificación dentro de la aplicación destinada a un usuario. */
class Notification extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'message',
        'type',
        'category',
        'is_read',
        'link',
        'created_by',
        'expires_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // ========== CONSTANTES ==========
    const TYPE_INFO = 'info';

    const TYPE_WARNING = 'warning';

    const TYPE_SUCCESS = 'success';

    const TYPE_ERROR = 'error';

    // ========== RELACIONES ==========
    /** Usuario destinatario de la notificación. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ========== SCOPES ==========
    /** Filtra las notificaciones que todavía no se han leído. */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /** Filtra las notificaciones por tipo. */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
