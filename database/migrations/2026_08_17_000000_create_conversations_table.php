<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id');
            $table->uuid('student_id');
            $table->uuid('evaluation_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->nullOnDelete();
            $table->unique(['teacher_id', 'student_id', 'evaluation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
