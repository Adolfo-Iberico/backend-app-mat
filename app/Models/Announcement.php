<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Anuncio global publicado por el administrador para los usuarios de la plataforma. */
class Announcement extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'body',
        'type',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ========== CONSTANTES ==========
    const TYPE_INFO = 'info';

    const TYPE_WARNING = 'warning';

    const TYPE_SUCCESS = 'success';

    // ========== RELACIONES ==========
    /** Usuario administrador que publicó el anuncio. */
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ========== SCOPES ==========
    /** Filtra los anuncios activos. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
