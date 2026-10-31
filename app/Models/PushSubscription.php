<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Suscripción de navegador para enviar notificaciones web push a un usuario. */
class PushSubscription extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'token',
        'platform',
        'p256dh',
        'auth',
        'device_name',
        'is_active',
    ];

    protected $hidden = ['p256dh', 'auth'];

    protected $table = 'device_tokens';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Usuario propietario de la suscripción. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Filtra las suscripciones web push activas. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Filtra las suscripciones de un usuario. */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId)->where('platform', 'web');
    }
}
