<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('grade');
            $table->string('section');
            $table->uuid('academic_period_id')->nullable();
            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->nullOnDelete();
            $table->uuid('coordinator_id')->nullable();
            $table->foreign('coordinator_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salones');
    }
};
