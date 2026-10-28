<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 — Integridad (3/5)
 *
 * Corrige sessions.user_id: era bigint(20) unsigned mientras que
 * users.id es char(36) UUID. Con SESSION_DRIVER=database activo,
 * una sesion de usuario autenticado no puede persistir su id en la
 * columna bigint (error de insercion) y el JOIN sessions<->users es
 * imposible por incompatibilidad de tipos.
 *
 * Se convierte a char(36) nullable. Los eventuales valores numericos
 * historicos (residuo del periodo previo al cambio) quedan como
 * cadenas huerfanas: no matchean ningun usuario y son inofensivos.
 * El indice sessions_user_id_index sobrevive a la modificacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
