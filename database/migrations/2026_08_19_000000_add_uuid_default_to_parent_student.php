<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asigna un default UUID a la columna `id` del pivot parent_student
        // para que belongsToMany()->attach() pueda insertar sin fallar.
        Schema::table('parent_student', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('(UUID())'))->change();
        });
    }

    public function down(): void
    {
        Schema::table('parent_student', function (Blueprint $table) {
            $table->uuid('id')->default(null)->change();
        });
    }
};
