<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $teacherRole = 'teacher';
        $studentRole = 'student';

        $this->teacher = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Teacher Test',
            'email' => 'teacher-lesson@test.com',
            'password' => 'password123',
            'role' => $teacherRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->student = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Student Test',
            'email' => 'student-lesson@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);
    }

    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create(array_merge([
            'id' => Str::uuid(),
            'title' => 'Test Lesson',
            'description' => 'A test lesson description',
            'content' => '<p>Lesson content here</p>',
            'teacher_id' => $this->teacher->id,
            'unit' => 'Unit 1',
            'topic' => 'Algebra',
            'difficulty' => 'basic',
            'tags' => ['math', 'algebra'],
            'estimated_time' => 45,
            'resources' => [],
            'order' => 1,
            'is_published' => false,
            'views_count' => 0,
        ], $overrides));
    }

    public function test_student_can_list_lessons(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $lesson = $this->createLesson(['is_published' => true]);

        $response = $this->getJson("{$this->baseUrl}/lessons");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_teacher_can_create_lesson(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->postJson("{$this->baseUrl}/lessons", [
            'title' => 'New Lesson',
            'description' => 'A brand new lesson',
            'content' => '<p>New content</p>',
            'difficulty' => 'basic',
            'unit' => 'Unit 2',
            'topic' => 'Geometry',
            'tags' => ['math', 'geometry'],
            'estimated_time' => 60,
            'order' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'content',
                    'difficulty',
                    'is_published',
                    'teacher_id',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'New Lesson')
            ->assertJsonPath('data.is_published', false);

        $this->assertDatabaseHas('lessons', [
            'title' => 'New Lesson',
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_teacher_lesson_content_is_sanitized_before_storage(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->postJson("{$this->baseUrl}/lessons", [
            'title' => 'Sanitized Lesson',
            'content' => '<p onclick="alert(1)">Safe</p><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">Link</a>',
            'difficulty' => 'basic',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', fn (string $content): bool => str_contains($content, '<p>Safe</p>')
                && ! str_contains($content, 'onclick')
                && ! str_contains($content, 'onerror')
                && ! str_contains($content, 'javascript:'));
    }

    public function test_student_cannot_create_lesson(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->postJson("{$this->baseUrl}/lessons", [
            'title' => 'Unauthorized Lesson',
            'description' => 'Should not work',
            'content' => '<p>No access</p>',
            'difficulty' => 'basic',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('lessons', [
            'title' => 'Unauthorized Lesson',
        ]);
    }

    public function test_teacher_can_update_lesson(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $lesson = $this->createLesson();

        $response = $this->putJson("{$this->baseUrl}/lessons/{$lesson->id}", [
            'title' => 'Updated Lesson Title',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Updated Lesson Title')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'title' => 'Updated Lesson Title',
        ]);
    }

    public function test_teacher_can_publish_lesson(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $lesson = $this->createLesson(['is_published' => false]);

        $response = $this->postJson("{$this->baseUrl}/lessons/{$lesson->id}/publish");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'is_published',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'is_published' => true,
        ]);
    }

    public function test_teacher_can_unpublish_lesson(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $lesson = $this->createLesson(['is_published' => true]);

        $response = $this->postJson("{$this->baseUrl}/lessons/{$lesson->id}/unpublish");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'is_published' => false,
        ]);
    }

    public function test_teacher_can_delete_lesson(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $lesson = $this->createLesson();

        $response = $this->deleteJson("{$this->baseUrl}/lessons/{$lesson->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
    }

    public function test_student_can_view_lesson(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $lesson = $this->createLesson(['is_published' => true]);

        $response = $this->getJson("{$this->baseUrl}/lessons/{$lesson->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'content',
                    'difficulty',
                    'teacher',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (string) $lesson->id)
            ->assertJsonPath('data.title', 'Test Lesson');
    }

    public function test_unauthenticated_cannot_view_lessons(): void
    {
        $response = $this->getJson("{$this->baseUrl}/lessons");

        $response->assertStatus(401);
    }

    public function test_lesson_not_found_returns_404(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $nonExistentId = Str::uuid();

        $response = $this->getJson("{$this->baseUrl}/lessons/{$nonExistentId}");

        $response->assertStatus(404);
    }
}
