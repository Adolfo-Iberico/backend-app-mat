<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseFeatureRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_dependent_endpoints_are_no_longer_registered(): void
    {
        foreach ([
            '/api/v1/courses',
            '/api/v1/salones',
            '/api/v1/games',
            '/api/v1/attendance',
        ] as $endpoint) {
            $this->getJson($endpoint)->assertNotFound();
        }
    }

    public function test_database_migration_preserves_v2_academic_tables_when_reversed_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_10_25_200200_remove_courses_and_roles.php');

        $migration->down();
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertTrue(Schema::hasTable('attendance'));
        $this->assertTrue(Schema::hasColumn('users', 'role_id'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertTrue(Schema::hasTable('attendance'));
        $this->assertTrue(Schema::hasColumn('users', 'role_id'));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }
}
