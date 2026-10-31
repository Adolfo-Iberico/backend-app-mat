<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_configs', function (Blueprint $table) {
            $table->string('tertiary_color')->default('#996100')->after('secondary_color');
            $table->string('background_color')->default('#f8f9ff')->after('tertiary_color');
            $table->string('surface_color')->default('#ffffff')->after('background_color');
        });
    }

    public function down(): void
    {
        Schema::table('institution_configs', function (Blueprint $table) {
            $table->dropColumn(['tertiary_color', 'background_color', 'surface_color']);
        });
    }
};
