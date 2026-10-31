<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable()->after('grade');
            $table->foreign('salon_id')->references('id')->on('salones')->nullOnDelete();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('name');
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropColumn('salon_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
