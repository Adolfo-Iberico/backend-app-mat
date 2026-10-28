<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\Achievement;
use App\Models\AcademicEvent;
use App\Models\PushSubscription;
use App\Services\GamificationService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class NewFeaturesApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $student;
    private User $teacher;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $studentRole = 'student';
        $teacherRole = 'teacher';
        $adminRole = 'admin';

        $this->admin = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Admin New',
            'email' => 'admin.new@test.com',
            'password' => 'password123',
            'role' => $adminRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->teacher = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Teacher New',
            'email' => 'teacher.new@test.com',
            'password' => 'password123',
            'role' => $teacherRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->student = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Student New',
            'email' => 'student.new@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        StudentProfile::create([
            'id' => Str::uuid(),
            'user_id' => $this->student->id,
            'academic_level' => 'basic',
            'current_streak' => 0,
            'total_xp' => 0,
            'level' => 1,
            'rank_points' => 0,
        ]);
    }

    // ============ GAMIFICACIÓN ============

    public function test_gamification_summary_returns_levels_and_achievements(): void
    {
        GamificationService::syncDefinitions();
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/gamification/summary");

        $response->assertOk()
            ->assertJsonPath('gamification.available', true)
            ->assertJsonPath('gamification.level', 1)
            ->assertJsonPath('gamification.total_xp', 0)
            ->assertJsonStructure([
                'gamification' => [
                    'level', 'total_xp', 'level_progress',
                    'achievements', 'unlocked_count', 'total_count',
                ],
            ]);
    }

    public function test_add_xp_updates_level_progress(): void
    {
        GamificationService::syncDefinitions();
        Sanctum::actingAs($this->student, ['web']);

        $profile = $this->student->studentProfile;
        $result = $profile->addXp(150);

        $this->assertGreaterThanOrEqual(1, $result['new_level']);
        $this->assertEquals(150, $profile->fresh()->total_xp);

        // Re-fetch summary
        $response = $this->getJson("{$this->baseUrl}/gamification/summary");
        $response->assertOk()
            ->assertJsonPath('gamification.total_xp', 150);
    }

    public function test_teacher_cannot_access_gamification(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $this->getJson("{$this->baseUrl}/gamification/summary")
            ->assertForbidden();
    }

    // ============ CALENDARIO ============

    public function test_teacher_can_create_and_list_events(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $create = $this->postJson("{$this->baseUrl}/calendar", [
            'title' => 'Quiz de Álgebra',
            'description' => 'Evaluación parcial',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'type' => 'exam',
            'all_day' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('event.title', 'Quiz de Álgebra');

        $list = $this->getJson("{$this->baseUrl}/calendar?start=2026-09-01&end=2026-09-30");
        $list->assertOk()
            ->assertJsonCount(1, 'events');
    }

    public function test_student_cannot_access_calendar(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $this->getJson("{$this->baseUrl}/calendar")
            ->assertForbidden();
    }

    public function test_teacher_cannot_delete_other_teacher_event(): void
    {
        $otherTeacher = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Other Teacher',
            'email' => 'other.teacher@test.com',
            'password' => 'password123',
            'role' => $this->teacher->roleName(),
            'is_active' => true,
            'provider' => 'email',
        ]);

        $event = AcademicEvent::create([
            'user_id' => $otherTeacher->id,
            'title' => 'Evento ajeno',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]);

        Sanctum::actingAs($this->teacher, ['web']);

        $this->deleteJson("{$this->baseUrl}/calendar/{$event->id}")
            ->assertNotFound();
    }

    // ============ TRADUCCIONES ADMIN ============

    public function test_admin_can_save_and_fetch_override(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $save = $this->postJson("{$this->baseUrl}/admin/translations", [
            'key' => 'nav.dashboard',
            'locale' => 'es',
            'value' => 'Panel de control',
            'group' => 'frontend',
        ]);

        $save->assertCreated()
            ->assertJsonPath('translation.value', 'Panel de control');

        $public = $this->getJson("{$this->baseUrl}/translations/overrides?locale=es");
        $public->assertOk()
            ->assertJsonPath('overrides.nav.dashboard', 'Panel de control');
    }

    public function test_teacher_cannot_access_admin_translations(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $this->getJson("{$this->baseUrl}/admin/translations")
            ->assertForbidden();
    }

    public function test_admin_can_bulk_update_translations(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $response = $this->postJson("{$this->baseUrl}/admin/translations/bulk", [
            'group' => 'frontend',
            'items' => [
                ['key' => 'nav.home', 'locale' => 'es', 'value' => 'Inicio'],
                ['key' => 'nav.home', 'locale' => 'en', 'value' => 'Home'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('updated', 2);
    }

    // ============ PUSH NOTIFICATIONS ============

    public function test_user_can_subscribe_and_see_config(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $subscribe = $this->postJson("{$this->baseUrl}/push/subscribe", [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'auth' => 'Mk_b42FbmBydR3es7-QB2Q',
        ]);

        $subscribe->assertCreated()
            ->assertJsonPath('subscription.id', fn ($id) => is_int($id) || is_string($id));

        $config = $this->getJson("{$this->baseUrl}/push/config");
        $config->assertOk()
            ->assertJsonStructure(['enabled', 'public_key']);
    }

    public function test_user_can_unsubscribe(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/unsub-endpoint';
        PushSubscription::create([
            'user_id' => $this->student->id,
            'endpoint' => $endpoint,
            'p256dh' => 'abc',
            'auth' => 'def',
            'is_active' => true,
        ]);

        $this->postJson("{$this->baseUrl}/push/unsubscribe", ['endpoint' => $endpoint])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $this->student->id,
            'endpoint' => $endpoint,
        ]);
    }

    // ============ NIVEL / XP ============

    public function test_cumulative_xp_level_formula(): void
    {
        $this->assertEquals(0, StudentProfile::cumulativeXpForLevel(1));
        $this->assertEquals(100, StudentProfile::cumulativeXpForLevel(2));
        $this->assertEquals(300, StudentProfile::cumulativeXpForLevel(3));
        $this->assertEquals(1, StudentProfile::levelFromXp(0));
        $this->assertEquals(2, StudentProfile::levelFromXp(100));
        $this->assertEquals(3, StudentProfile::levelFromXp(300));
    }
}