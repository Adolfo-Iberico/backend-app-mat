<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SchemaV2MigrationGuardTest extends TestCase
{
    public function test_laravel_migrations_refuse_to_modify_schema_v2_baseline(): void
    {
        Schema::create('migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
        DB::table('migrations')->insert([
            'migration' => '2026_10_22_000000_baseline_schema_v2',
            'batch' => 1,
        ]);
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $migration = require database_path('migrations/0001_01_01_000001_create_cache_table.php');

        try {
            $migration->up();
            $this->fail('The schema v2 baseline must not be migrated as a fresh Laravel database.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Detected the schema.sql v2 baseline', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('roles'));
    }
}
