<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\InstitutionConfig;
use App\Models\AcademicPeriod;
use Laravel\Sanctum\Sanctum;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $admin;
    private User $teacher;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = 'admin';
        $teacherRole = 'teacher';
        $studentRole = 'student';

        $this->admin = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => $adminRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->teacher = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Teacher Test',
            'email' => 'teacher@test.com',
            'password' => 'password123',
            'role' => $teacherRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->student = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Student Test',
            'email' => 'student@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);
    }

    // --- User Management ---

    public function test_admin_can_get_users(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->getJson("{$this->baseUrl}/admin/users");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'full_name', 'email', 'is_active'],
                ],
            ]);
    }

    public function test_teacher_cannot_access_users(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/admin/users");

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->postJson("{$this->baseUrl}/admin/users", [
            'full_name' => 'New Student',
            'email' => 'newstudent@test.com',
            'password' => 'password123',
            'role' => 'student',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user'])
            ->assertJsonPath('user.email', 'newstudent@test.com');
    }

    public function test_admin_cannot_create_admin_user(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->postJson("{$this->baseUrl}/admin/users", [
            'full_name' => 'Fake Admin',
            'email' => 'fakeadmin@test.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status());

        $this->assertDatabaseMissing('users', ['email' => 'fakeadmin@test.com']);
    }

    public function test_admin_can_update_user(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->putJson("{$this->baseUrl}/admin/users/{$this->student->id}", [
            'full_name' => 'Updated Student',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user'])
            ->assertJsonPath('user.full_name', 'Updated Student');
    }

    public function test_admin_can_delete_user(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $adminRole = 'admin';
        User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Second Admin',
            'email' => 'secondadmin@test.com',
            'password' => 'password123',
            'role' => $adminRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $studentRole = 'student';
        $userToDelete = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Delete Me',
            'email' => 'deleteme@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $response = $this->deleteJson("{$this->baseUrl}/admin/users/{$userToDelete->id}");

        $response->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_admin_cannot_delete_self(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->deleteJson("{$this->baseUrl}/admin/users/{$this->admin->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_toggle_user_active(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->postJson("{$this->baseUrl}/admin/users/{$this->teacher->id}/deactivate");

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $response = $this->postJson("{$this->baseUrl}/admin/users/{$this->teacher->id}/activate");

        $response->assertOk()
            ->assertJsonStructure(['message']);
    }

    // --- Config ---

    public function test_admin_can_get_config(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->getJson("{$this->baseUrl}/admin/config");

        $response->assertOk()
            ->assertJsonStructure([
                'institution_name',
                'primary_color',
                'secondary_color',
            ]);
    }

    public function test_public_config_does_not_create_record(): void
    {
        $response = $this->getJson("{$this->baseUrl}/config");

        $response->assertOk();

        $this->assertDatabaseCount('institution_configs', 0);
    }

    // --- Dashboard ---

    public function test_admin_can_get_dashboard(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->getJson("{$this->baseUrl}/dashboard/admin");

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total_users',
                    'total_students',
                    'total_teachers',
                    'total_lessons',
                    'published_lessons',
                    'total_evaluations',
                ],
                'recent_users',
            ]);
    }

    // --- Periods ---

    public function test_admin_can_create_period(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->postJson("{$this->baseUrl}/admin/periods", [
            'name' => '2026-I',
            'start_date' => '2026-01-15',
            'end_date' => '2026-06-30',
            'description' => 'First semester 2026',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'period'])
            ->assertJsonPath('period.name', '2026-I');
    }

    public function test_admin_can_get_periods(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        AcademicPeriod::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => '2026-II',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-15',
            'is_active' => true,
        ]);

        $response = $this->getJson("{$this->baseUrl}/admin/periods");

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['id', 'name', 'start_date', 'end_date', 'is_active'],
            ]);
    }
}
