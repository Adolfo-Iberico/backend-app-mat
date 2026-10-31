<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id');
            $table->uuid('lesson_id')->nullable();
            $table->string('title');
            $table->text('body');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->nullOnDelete();
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_threads');
    }
};
