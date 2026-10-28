<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Traducción localizada de una cadena del sistema. */
class SystemTranslation extends Model
{
    public const LOCALES = ['es', 'en', 'qu'];

    protected $fillable = [
        'key',
        'locale',
        'value',
        'group',
    ];
}
