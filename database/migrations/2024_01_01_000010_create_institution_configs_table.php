<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('institution_name');
            $table->string('primary_color')->default('#004AC6');
            $table->string('secondary_color')->default('#006C49');
            $table->string('logo')->nullable();
            $table->json('email_notifications')->nullable();
            $table->string('backup_frequency')->default('daily');
            $table->timestamp('last_backup')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_configs');
    }
};
