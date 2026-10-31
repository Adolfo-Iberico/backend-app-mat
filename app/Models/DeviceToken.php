<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Token de un dispositivo registrado para recibir notificaciones FCM. */
class DeviceToken extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'token',
        'platform',
        'device_name',
        'device_model',
        'is_active',
        'last_used_at',
    ];

    protected $hidden = [
        'token',
        'p256dh',
        'auth',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    const PLATFORM_ANDROID = 'android';

    const PLATFORM_IOS = 'ios';

    const PLATFORM_WEB = 'web';

    /** Usuario propietario del dispositivo. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Filtra los tokens activos. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filtra los tokens de un usuario. */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /** Filtra los tokens por plataforma. */
    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }
}
