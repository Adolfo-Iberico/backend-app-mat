<?php

namespace Tests\Feature;

use App\Models\EvaluationResult;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';

    private function createUser(string $roleName, array $overrides = []): User
    {
        $role = $roleName;

        return User::create(array_merge([
            'id' => Str::uuid(),
            'email' => $roleName.'_'.Str::random(6).'@example.com',
            'full_name' => ucfirst($roleName).' User',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
            'provider' => 'email',
        ], $overrides));
    }

    public function test_captcha_does_not_return_plaintext_code(): void
    {
        $response = $this->getJson("{$this->baseUrl}/guest/captcha");

        $response->assertOk()
            ->assertJsonMissing(['captcha_code'])
            ->assertJsonStructure(['captcha_token', 'captcha_image']);
    }

    public function test_cannot_self_register_as_teacher(): void
    {
        $response = $this->postJson("{$this->baseUrl}/auth/register", [
            'full_name' => 'Evil Teacher',
            'email' => 'evil_teacher@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'teacher',
            'department' => 'Math',
            'specialization' => 'Algebra',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'evil_teacher@example.com']);
    }

    public function test_student_cannot_report_cheating_on_others_attempt(): void
    {
        $studentA = $this->createUser('student');
        $studentB = $this->createUser('student');
        $teacher = $this->createUser('teacher');

        $exam = Exam::create([
            'id' => Str::uuid(),
            'teacher_id' => $teacher->id,
            'title' => 'Math Exam',
            'is_active' => true,
        ]);

        $attempt = EvaluationResult::create([
            'id' => Str::uuid(),
            'user_id' => $studentB->id,
            'evaluation_id' => $exam->id,
            'max_score' => 20,
            'total_questions' => 1,
            'status' => 'pending',
            'started_at' => now(),
            'attempt_number' => 1,
            'tab_switch_count' => 0,
            'cheat_log' => [],
        ]);

        Sanctum::actingAs($studentA, ['student']);

        $response = $this->postJson("{$this->baseUrl}/exams/attempts/{$attempt->id}/cheat", [
            'event' => 'tab_switch',
        ]);

        $response->assertStatus(403);
    }

    public function test_parent_cannot_see_correct_answers_in_exam(): void
    {
        $parent = $this->createUser('parent');
        $teacher = $this->createUser('teacher');

        $exam = Exam::create([
            'id' => Str::uuid(),
            'teacher_id' => $teacher->id,
            'title' => 'Secret Exam',
            'is_active' => true,
        ]);

        Question::create([
            'id' => Str::uuid(),
            'evaluation_id' => $exam->id,
            'type' => 'multiple_choice',
            'question_text' => 'What is 2+2?',
            'options' => ['3', '4', '5'],
            'correct_answer' => '4',
            'order' => 1,
        ]);

        Sanctum::actingAs($parent, ['parent']);

        $response = $this->getJson("{$this->baseUrl}/exams/{$exam->id}");

        $response->assertOk()
            ->assertJsonMissing(['correct_answer']);
    }

    public function test_self_registration_only_allows_student_or_parent(): void
    {
        $response = $this->postJson("{$this->baseUrl}/auth/register", [
            'full_name' => 'Bad Role',
            'email' => 'bad_role@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
    }

    public function test_cache_response_middleware_returns_304_without_crashing(): void
    {
        $student = $this->createUser('student');

        // primera petición devuelve 200 con ETag
        $response = $this->actingAs($student)->getJson("{$this->baseUrl}/user/profile");
        $response->assertOk();
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag, 'La respuesta debe incluir un ETag.');

        // segunda petición con el mismo If-None-Match devuelve 304 (sin lanzar
        // "ResponseFactory::setNotModified does not exist")
        $response2 = $this->actingAs($student)->getJson("{$this->baseUrl}/user/profile", [
            'If-None-Match' => $etag,
        ]);
        $response2->assertStatus(304);
    }
}
