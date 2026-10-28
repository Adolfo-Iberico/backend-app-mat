<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\ForumThread;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForumMessagingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';

    private User $teacher;

    private User $student;

    private User $otherStudent;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $teacherRole = 'teacher';
        $studentRole = 'student';
        $adminRole = 'admin';

        $this->teacher = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Teacher F6',
            'email' => 'teacher-f6@test.com',
            'password' => 'password123',
            'role' => $teacherRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->student = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Student F6',
            'email' => 'student-f6@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->otherStudent = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Other Student',
            'email' => 'other-f6@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->admin = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Admin F6',
            'email' => 'admin-f6@test.com',
            'password' => 'password123',
            'role' => $adminRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        // Relación académica: el docente tiene una evaluación tomada por student (no por otherStudent)
        $evaluation = Evaluation::create([
            'id' => Str::uuid(),
            'title' => 'F6 Evaluation',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
        ]);

        EvaluationResult::create([
            'id' => Str::uuid(),
            'user_id' => $this->student->id,
            'evaluation_id' => $evaluation->id,
            'score' => 15,
            'max_score' => 20,
            'correct_answers' => 12,
            'total_questions' => 15,
            'time_taken' => 1200,
            'status' => 'completed',
        ]);
    }

    // ================= CONVERSACIONES =================

    public function test_unauthenticated_user_cannot_access_conversations(): void
    {
        $this->getJson("{$this->baseUrl}/conversations")->assertUnauthorized();
    }

    public function test_teacher_can_start_conversation_with_linked_student(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->postJson("{$this->baseUrl}/conversations", [
            'recipient_id' => $this->student->id,
            'body' => 'Hola, veamos tu avance',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('conversation.student_id', (string) $this->student->id);
    }

    public function test_teacher_cannot_start_conversation_with_unlinked_student(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->postJson("{$this->baseUrl}/conversations", [
            'recipient_id' => $this->otherStudent->id,
            'body' => 'Hola',
        ]);

        $response->assertStatus(403);
    }

    public function test_student_cannot_access_conversation_they_do_not_participate_in(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);
        $conversation = Conversation::create([
            'id' => Str::uuid(),
            'teacher_id' => $this->teacher->id,
            'student_id' => $this->student->id,
            'last_message_at' => now(),
        ]);

        // otherStudent no participa
        Sanctum::actingAs($this->otherStudent, ['web']);
        $response = $this->getJson("{$this->baseUrl}/conversations/{$conversation->id}");
        $response->assertStatus(403);

        // admin tampoco
        Sanctum::actingAs($this->admin, ['web']);
        $response = $this->getJson("{$this->baseUrl}/conversations/{$conversation->id}");
        $response->assertStatus(403);
    }

    public function test_participant_can_reply_in_conversation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);
        $conversation = Conversation::create([
            'id' => Str::uuid(),
            'teacher_id' => $this->teacher->id,
            'student_id' => $this->student->id,
            'last_message_at' => now(),
        ]);

        Sanctum::actingAs($this->student, ['web']);
        $response = $this->postJson("{$this->baseUrl}/conversations/{$conversation->id}/reply", [
            'body' => 'Entendido, gracias',
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.sender_id', (string) $this->student->id);
    }

    // ================= FORO =================

    public function test_teacher_can_create_thread(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->postJson("{$this->baseUrl}/forum", [
            'title' => 'Dudas del tema',
            'body' => 'Pregunten aquí',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.teacher_id', (string) $this->teacher->id);
    }

    public function test_student_can_create_thread_on_lesson(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $lesson = Lesson::create([
            'id' => Str::uuid(),
            'title' => 'Leccion de foro',
            'description' => 'Leccion de prueba',
            'content' => '<p>Contenido</p>',
            'teacher_id' => $this->teacher->id,
            'unit' => 'Unit 1',
            'topic' => 'Algebra',
            'difficulty' => 'basic',
            'tags' => ['math'],
            'estimated_time' => 30,
            'resources' => [],
            'order' => 1,
            'is_published' => true,
            'views_count' => 0,
        ]);

        $response = $this->postJson("{$this->baseUrl}/forum", [
            'title' => 'Duda sobre la leccion',
            'body' => 'No entiendo el paso 2 del ejemplo',
            'lesson_id' => $lesson->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.teacher_id', (string) $this->teacher->id)
            ->assertJsonPath('data.author_id', (string) $this->student->id)
            ->assertJsonPath('data.lesson_id', (string) $lesson->id);

        // El autor puede ver su propio hilo y responderlo.
        $view = $this->getJson("{$this->baseUrl}/forum/{$response->json('data.id')}");
        $view->assertStatus(200);

        $reply = $this->postJson("{$this->baseUrl}/forum/{$response->json('data.id')}/post", [
            'body' => 'Agrego mas detalle a mi duda',
        ]);
        $reply->assertStatus(201);

        // El docente de la leccion ve el hilo del estudiante.
        Sanctum::actingAs($this->teacher, ['web']);
        $teacherView = $this->getJson("{$this->baseUrl}/forum/{$response->json('data.id')}");
        $teacherView->assertStatus(200);

        // Un estudiante sin relacion con el docente ni autoria no lo ve.
        Sanctum::actingAs($this->otherStudent, ['web']);
        $otherView = $this->getJson("{$this->baseUrl}/forum/{$response->json('data.id')}");
        $otherView->assertStatus(403);
    }

    public function test_student_cannot_create_thread_without_lesson(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->postJson("{$this->baseUrl}/forum", [
            'title' => 'Hilo sin leccion',
            'body' => 'deberia requerir leccion',
        ]);

        $response->assertStatus(422);
    }

    public function test_linked_student_can_view_and_post_in_thread(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);
        $thread = ForumThread::create([
            'id' => Str::uuid(),
            'teacher_id' => $this->teacher->id,
            'title' => 'Dudas',
            'body' => 'Pregunten',
        ]);

        Sanctum::actingAs($this->student, ['web']);
        $view = $this->getJson("{$this->baseUrl}/forum/{$thread->id}");
        $view->assertStatus(200);

        $post = $this->postJson("{$this->baseUrl}/forum/{$thread->id}/post", [
            'body' => 'Tengo una duda',
        ]);
        $post->assertStatus(201);
    }

    public function test_unlinked_student_cannot_view_thread(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);
        $thread = ForumThread::create([
            'id' => Str::uuid(),
            'teacher_id' => $this->teacher->id,
            'title' => 'Privado',
            'body' => 'solo clase',
        ]);

        Sanctum::actingAs($this->otherStudent, ['web']);
        $response = $this->getJson("{$this->baseUrl}/forum/{$thread->id}");
        $response->assertStatus(403);
    }
}
