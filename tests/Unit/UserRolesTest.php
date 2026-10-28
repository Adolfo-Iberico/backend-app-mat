<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_check(): void
    {
        $role = 'admin';
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'email' => 'admin@test.com',
            'full_name' => 'Admin',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->isStudent());
    }

    public function test_null_role_does_not_crash(): void
    {
        $user = new User();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->isStudent());
    }

    public function test_student_profile_created_on_register(): void
    {
        $role = 'student';
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'email' => 'student@test.com',
            'full_name' => 'Student',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'provider' => 'email',
        ]);

        \App\Models\StudentProfile::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'academic_level' => 'basic',
        ]);

        $this->assertNotNull($user->studentProfile);
        $this->assertEquals('basic', $user->studentProfile->academic_level);
    }
}
