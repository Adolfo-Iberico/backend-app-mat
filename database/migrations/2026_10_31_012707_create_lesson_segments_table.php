<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lesson_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['text', 'video', 'pdf', 'quiz', 'link']);
            $table->text('content')->nullable();
            $table->string('url')->nullable();
            $table->integer('order')->default(0);
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_segments');
    }
};
