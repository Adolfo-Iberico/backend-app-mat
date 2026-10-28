<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 — Integridad (5/5)
 *
 * Documenta en el repo la tabla audit_logs, que existe en la BD de
 * analisis (mentematica_bd, 819 filas de historia: user.created,
 * lesson.created, evaluation.created...) pero fue creada fuera del
 * control de migraciones: no hay migracion ni modelo en el codigo, y
 * la escritura dejo de ocurrir el 2026-08-18 cuando esa version del
 * codigo se perdio.
 *
 * Guarda con hasTable: en entornos donde ya existe (con datos) no hace
 * nada; donde no existe, la crea para alinear los esquemas y dejarla
 * disponible para volver a registrar auditoria.
 *
 * PENDIENTE (Fase 2 recomendada): crear el modelo AuditLog y el
 * middleware que la escriba, para restaurar el registro de cambios
 * relevantes en notas, matriculas, usuarios y configuraciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('action');
            $table->string('auditable_type');
            $table->uuid('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->string('platform', 20)->default('unknown');
            $table->smallInteger('status_code');
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
            $table->index(['method', 'status_code']);
            $table->index('action');
            $table->index('auditable_type');
        });
    }

    public function down(): void
    {
        // Solo se elimina si esta vacia: nunca se destruye historial de auditoria.
        if (Schema::hasTable('audit_logs') && DB::table('audit_logs')->count() === 0) {
            Schema::dropIfExists('audit_logs');
        }
    }
};
