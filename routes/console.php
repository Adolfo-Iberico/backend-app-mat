<?php

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Schedule;

// Tareas programadas de mantenimiento de tokens y respaldos; Laravel Scheduler las ejecuta a diario.
// El pruning de Sanctum conserva el umbral de expiración configurado en el comando.
Schedule::command('sanctum:prune')->daily();

// Elimina tokens de dispositivo sin uso durante 90 días, todos los días a las 02:00.
Schedule::call(function () {
    DeviceToken::where('last_used_at', '<', now()->subDays(90))->delete();
})->daily()->at('02:00');

// Crea un respaldo diario a las 03:00 y poda archivos antiguos conservando uno por día.
Schedule::command('aulamate:backup --prune')->daily()->at('03:00');
