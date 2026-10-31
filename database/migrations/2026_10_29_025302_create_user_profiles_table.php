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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->onDelete('cascade');
            $table->enum('academic_level', ['basic', 'intermediate', 'advanced'])->nullable();
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('rank_points')->default(0);
            $table->integer('current_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->string('department')->nullable();
            $table->string('specialization')->nullable();
            $table->integer('years_experience')->default(0);
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('grade')->nullable();
            $table->string('salon')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
