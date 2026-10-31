<?php

namespace Tests\Unit;

use App\Models\PushSubscription;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_check(): void
    {
        $role = 'admin';
        $user = User::create([
            'id' => Str::uuid(),
            'email' => 'admin@test.com',
            'full_name' => 'Admin',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->assertTrue($user->isAdmin());
        $this->assertSame('admin', $user->role->name);
        $this->assertSame('admin', $user->roleName());
        $this->assertSame('admin', $user->toArray()['role']['name']);
        $this->assertNotNull($user->role_id);
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->isStudent());
    }

    public function test_null_role_does_not_crash(): void
    {
        $user = new User;

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->isStudent());
    }

    public function test_student_profile_created_on_register(): void
    {
        $role = 'student';
        $user = User::create([
            'id' => Str::uuid(),
            'email' => 'student@test.com',
            'full_name' => 'Student',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'academic_level' => 'basic',
        ]);

        $this->assertNotNull($user->studentProfile);
        $this->assertEquals('basic', $user->studentProfile->academic_level);
        $this->assertSame('user_profiles', $profile->getTable());

        $xp = $profile->addXp(100);
        $this->assertSame(100, $xp['total_xp']);
        $this->assertSame(100, $profile->fresh()->xp);
    }

    public function test_web_push_subscriptions_use_consolidated_device_tokens_table(): void
    {
        $user = User::create([
            'id' => Str::uuid(),
            'email' => 'push@test.com',
            'full_name' => 'Push User',
            'password' => 'password',
            'role' => 'student',
            'is_active' => true,
            'provider' => 'email',
        ]);

        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'token' => 'https://push.example.test/subscription',
            'platform' => 'web',
            'p256dh' => 'public-key',
            'auth' => 'auth-key',
            'is_active' => true,
        ]);

        $this->assertSame('device_tokens', $subscription->getTable());
        $this->assertCount(1, $user->pushSubscriptions);
    }
}
