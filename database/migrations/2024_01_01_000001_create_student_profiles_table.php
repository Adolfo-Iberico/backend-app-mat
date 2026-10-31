<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->enum('academic_level', ['basic', 'intermediate', 'advanced'])->default('basic');
            $table->integer('total_lessons_completed')->default(0);
            $table->float('average_score')->default(0);
            $table->integer('total_time_spent')->default(0);
            $table->integer('current_streak')->default(0);
            $table->json('badges')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
