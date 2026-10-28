<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Evaluation;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\EvaluationResult;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Carbon;

class EvaluationApiTest extends TestCase
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
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Teacher Test',
            'email' => 'teacher-eval@test.com',
            'password' => 'password123',
            'role' => $teacherRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->student = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'full_name' => 'Student Test',
            'email' => 'student-eval@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
            'grade' => '1ro',
        ]);
    }

    /**
     * Lección del grado del estudiante de prueba, para cumplir el
     * aislamiento por grado exigido al enviar evaluaciones.
     */
    private function gradedLessonId(): string
    {
        $lesson = Lesson::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Lección de prueba',
            'content' => 'Contenido de la lección',
            'teacher_id' => $this->teacher->id,
            'grade' => '1ro',
        ]);

        return $lesson->id;
    }

    public function test_teacher_can_list_evaluations(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Midterm Exam',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
        ]);

        $response = $this->getJson("{$this->baseUrl}/evaluations");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'title', 'type', 'difficulty'],
                    ],
                ],
            ]);
    }

    public function test_student_can_list_evaluations(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Published Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_INTERMEDIATE,
            'is_published' => true,
        ]);

        Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Draft Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $response = $this->getJson("{$this->baseUrl}/evaluations");

        $response->assertOk()->assertJsonStructure(['success', 'data']);
    }

    public function test_teacher_can_create_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $payload = [
            'title' => 'New Quiz',
            'description' => 'A test quiz',
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'time_limit' => 30,
        ];

        $response = $this->postJson("{$this->baseUrl}/evaluations", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'type', 'difficulty', 'teacher_id'],
            ])
            ->assertJsonPath('data.title', 'New Quiz')
            ->assertJsonPath('data.type', Evaluation::TYPE_QUIZ);
    }

    public function test_student_cannot_create_evaluation(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $payload = [
            'title' => 'Unauthorized Quiz',
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
        ];

        $response = $this->postJson("{$this->baseUrl}/evaluations", $payload);

        $response->assertStatus(403);
    }

    public function test_teacher_can_update_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Old Title',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $response = $this->putJson("{$this->baseUrl}/evaluations/{$evaluation->id}", [
            'title' => 'Updated Title',
            'difficulty' => Evaluation::DIFFICULTY_ADVANCED,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.difficulty', Evaluation::DIFFICULTY_ADVANCED);
    }

    public function test_teacher_can_add_question(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Quiz With Questions',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $payload = [
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'What is 2+2?',
            'options' => [
                ['label' => 'A', 'value' => '3'],
                ['label' => 'B', 'value' => '4'],
                ['label' => 'C', 'value' => '5'],
                ['label' => 'D', 'value' => '6'],
            ],
            'correct_answer' => '4',
            'points' => 5,
            'order' => 0,
        ];

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/questions", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'type', 'question_text', 'correct_answer', 'points'],
            ])
            ->assertJsonPath('data.question_text', 'What is 2+2?')
            ->assertJsonPath('data.points', 5);
    }

    public function test_teacher_can_publish_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Ready To Publish',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_INTERMEDIATE,
            'is_published' => false,
        ]);

        Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_FILL_BLANK,
            'question_text' => 'Solve: x + 3 = 7',
            'correct_answer' => '4',
            'points' => 5,
            'order' => 0,
        ]);

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'is_published'],
            ]);
    }

    public function test_cannot_publish_without_questions(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Empty Evaluation',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => false,
        ]);

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/publish");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_student_can_submit_evaluation(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Submittable Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
            'max_attempts' => 3,
            'time_limit' => 30,
            'lesson_id' => $this->gradedLessonId(),
        ]);

        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'What is 3 x 3?',
            'options' => [
                ['label' => 'A', 'value' => '6'],
                ['label' => 'B', 'value' => '9'],
                ['label' => 'C', 'value' => '12'],
            ],
            'correct_answer' => '9',
            'points' => 10,
            'order' => 0,
        ]);

        Sanctum::actingAs($this->student, ['web']);

        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => '9'],
            ],
            'time_taken' => 600,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'result' => ['id', 'score', 'status'],
                    'score',
                    'correct_answers',
                    'total_questions',
                ],
            ])
            ->assertJsonPath('data.total_questions', 1);
    }

    public function test_numeric_answer_key_is_resolved_to_its_option_and_duration_uses_server_times(): void
    {
        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Legacy answer key',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
            'max_attempts' => 2,
            'time_limit' => 30,
            'lesson_id' => $this->gradedLessonId(),
        ]);
        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => '¿Qué significa que sean congruentes?',
            'options' => [
                ['label' => 'A', 'value' => 'Tienen la misma forma y tamaño'],
                ['label' => 'B', 'value' => 'Tienen distinto tamaño'],
            ],
            'correct_answer' => '1',
            'points' => 2,
        ]);

        Sanctum::actingAs($this->student, ['web']);
        $started = Carbon::parse('2026-10-17 15:00:00', 'America/Lima');
        Carbon::setTestNow($started);
        $startResponse = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/start");
        $attemptId = $startResponse->assertCreated()->json('data.id');
        $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/attempts/{$attemptId}/tab-switch")
            ->assertOk()
            ->assertJsonPath('tab_switch_count', 1);

        Carbon::setTestNow($started->copy()->addSeconds(95));
        $submitResponse = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'attempt_id' => $attemptId,
            'answers' => [
                ['question_id' => $question->id, 'answer' => 'Tienen la misma forma y tamaño'],
            ],
        ]);

        $submitResponse->assertOk()
            ->assertJsonPath('data.score', 20)
            ->assertJsonPath('data.result.time_taken', 95)
            ->assertJsonPath('data.result.tab_switch_count', 1);
        $this->assertSame(
            'A. Tienen la misma forma y tamaño',
            $question->fresh()->correct_answer_display
        );
        $this->assertSame(95, $submitResponse->json('data.result.time_taken'));
        Carbon::setTestNow();
    }

    public function test_json_encoded_legacy_options_are_displayed_and_graded(): void
    {
        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Legacy options',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
            'max_attempts' => 1,
            'lesson_id' => $this->gradedLessonId(),
        ]);
        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'Elige la alternativa correcta',
            'options' => json_encode(['Primera alternativa', 'Segunda alternativa']),
            'correct_answer' => '2',
            'points' => 1,
        ]);

        Sanctum::actingAs($this->student, ['web']);
        $this->getJson("{$this->baseUrl}/evaluations/{$evaluation->id}/questions")
            ->assertOk()
            ->assertJsonPath('data.0.options', '["Primera alternativa","Segunda alternativa"]');

        $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => 'Segunda alternativa'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 20)
            ->assertJsonPath('data.correct_answers', 1);
    }

    public function test_numeric_answer_matching_the_raw_key_is_graded_correctly(): void
    {
        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Clave numerica cruda',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
            'max_attempts' => 1,
            'lesson_id' => $this->gradedLessonId(),
        ]);
        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'Cual opcion es correcta?',
            'options' => [
                ['label' => 'A', 'value' => 'Primera'],
                ['label' => 'B', 'value' => 'Segunda'],
                ['label' => 'C', 'value' => 'Tercera'],
            ],
            'correct_answer' => '1',
            'points' => 3,
        ]);

        Sanctum::actingAs($this->student, ['web']);
        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => '1'],
            ],
        ]);

        // La clave '1' (1-based, opcion A) y la respuesta '1' deben coincidir
        // aunque submittedAnswer() resuelva indices 0-based primero.
        $response->assertOk()
            ->assertJsonPath('data.score', 20)
            ->assertJsonPath('data.correct_answers', 1);
        $this->assertTrue(EvaluationResult::first()->studentAnswers()->first()->is_correct);
    }

    public function test_text_response_receives_partial_credit_from_groq(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake([
            config('services.groq.url') => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'grades' => [[
                                'index' => 0,
                                'points_awarded' => 0.5,
                                'feedback' => 'Respuesta parcialmente correcta',
                            ]],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Partial credit',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_QUIZ,
            'difficulty' => Evaluation::DIFFICULTY_BASIC,
            'is_published' => true,
            'max_attempts' => 2,
            'lesson_id' => $this->gradedLessonId(),
        ]);
        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_FILL_BLANK,
            'question_text' => 'Explica qué significa congruencia.',
            'correct_answer' => 'Misma forma y tamaño',
            'points' => 1,
        ]);

        Sanctum::actingAs($this->student, ['web']);
        $response = $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => 'Figuras iguales'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.score', 10);
        $this->assertSame(0.5, (float) EvaluationResult::first()->studentAnswers()->first()->points_earned);
    }

    public function test_groq_chat_returns_a_streamed_answer_and_uses_conversation_history(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake([
            config('services.groq.url') => Http::response([
                'choices' => [[
                    'message' => ['content' => '¡Claro! Una fracción representa partes iguales de un todo.'],
                ]],
            ]),
        ]);
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->postJson("{$this->baseUrl}/ai/chat", [
            'message' => '¿Qué es una fracción?',
            'messages' => [
                ['role' => 'user', 'content' => 'Ayúdame con matemáticas'],
                ['role' => 'assistant', 'content' => 'Claro, ¿qué tema?'],
                ['role' => 'user', 'content' => '¿Qué es una fracción?'],
            ],
        ]);

        $response->assertOk()
            ->assertHeader('content-type', 'text/event-stream; charset=UTF-8');
        $this->assertStringContainsString('fracción representa partes iguales', $response->streamedContent());
        Http::assertSent(fn ($request) =>
            $request->url() === config('services.groq.url')
            && $request['messages'][1]['content'] === 'Ayúdame con matemáticas'
            && $request['messages'][3]['content'] === '¿Qué es una fracción?'
        );
    }

    public function test_result_pdf_displays_utc_timestamps_in_lima_time(): void
    {
        $evaluation = Evaluation::make(['title' => 'Evaluación horaria']);
        $evaluation->setRelation('teacher', $this->teacher);
        $evaluation->setRelation('lesson', null);

        $result = EvaluationResult::make([
            'score' => 20,
            'correct_answers' => 1,
            'total_questions' => 1,
            'started_at' => Carbon::parse('2026-10-17 20:00:00', 'UTC'),
            'completed_at' => Carbon::parse('2026-10-17 20:08:00', 'UTC'),
            'time_taken' => 480,
            'attempt_number' => 1,
            'tab_switch_count' => 0,
        ]);
        $result->setRelation('studentAnswers', collect());

        $html = view('evaluations.result-pdf', [
            'evaluation' => $evaluation,
            'result' => $result,
            'student' => $this->student,
        ])->render();

        $this->assertStringContainsString('17/10/2026 15:00', $html);
        $this->assertStringContainsString('17/10/2026 15:08', $html);
    }

    public function test_teacher_can_view_results(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $evaluation = Evaluation::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'title' => 'Evaluated Quiz',
            'teacher_id' => $this->teacher->id,
            'type' => Evaluation::TYPE_EXAM,
            'difficulty' => Evaluation::DIFFICULTY_ADVANCED,
            'is_published' => true,
        ]);

        $question = Question::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'evaluation_id' => $evaluation->id,
            'type' => Question::TYPE_FORMULA,
            'question_text' => 'Calculate 5 + 5',
            'correct_answer' => '10',
            'points' => 10,
            'order' => 0,
        ]);

        Sanctum::actingAs($this->student, ['web']);

        $this->postJson("{$this->baseUrl}/evaluations/{$evaluation->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => '10'],
            ],
            'time_taken' => 300,
        ]);

        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/evaluations/{$evaluation->id}/results");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'stats' => [
                    'total_submissions',
                    'average_score',
                    'max_score',
                    'min_score',
                    'passing_rate',
                ],
            ]);
    }
}
