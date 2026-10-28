<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1';
    private User $teacher;
    private User $student;
    private User $admin;
    private Lesson $lesson;
    private Evaluation $evaluation;

    protected function setUp(): void
    {
        parent::setUp();

        $studentRole = 'student';
        $teacherRole = 'teacher';
        $adminRole = 'admin';

        $this->teacher = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Teacher Reports',
            'email' => 'teacher.reports@test.com',
            'password' => 'password123',
            'role' => $teacherRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        TeacherProfile::create([
            'id' => Str::uuid(),
            'user_id' => $this->teacher->id,
            'department' => 'Mathematics',
            'specialization' => 'Calculus',
            'years_experience' => 8,
        ]);

        $this->student = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Student Reports',
            'email' => 'student.reports@test.com',
            'password' => 'password123',
            'role' => $studentRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        StudentProfile::create([
            'id' => Str::uuid(),
            'user_id' => $this->student->id,
            'academic_level' => 'intermediate',
            'current_streak' => 10,
        ]);

        $this->admin = User::create([
            'id' => Str::uuid(),
            'full_name' => 'Admin Reports',
            'email' => 'admin.reports@test.com',
            'password' => 'password123',
            'role' => $adminRole,
            'is_active' => true,
            'provider' => 'email',
        ]);

        $this->lesson = Lesson::create([
            'id' => Str::uuid(),
            'title' => 'Cálculo Diferencial',
            'description' => 'Derivadas e integrales',
            'content' => '<p>Contenido de cálculo</p>',
            'teacher_id' => $this->teacher->id,
            'unit' => 1,
            'topic' => 'Calculus',
            'difficulty' => 'intermediate',
            'is_published' => true,
            'published_at' => now(),
            'estimated_time' => 45,
            'order' => 1,
        ]);

        $this->evaluation = Evaluation::create([
            'id' => Str::uuid(),
            'title' => 'Parcial 1 - Cálculo',
            'description' => 'Primera evaluación parcial',
            'teacher_id' => $this->teacher->id,
            'lesson_id' => $this->lesson->id,
            'type' => 'exam',
            'difficulty' => 'intermediate',
            'time_limit' => 90,
            'due_date' => now()->addDays(14),
            'is_published' => true,
            'published_at' => now(),
            'auto_correct' => true,
            'randomize_questions' => false,
            'max_attempts' => 2,
            'total_questions' => 15,
            'total_points' => 20,
        ]);

        EvaluationResult::create([
            'id' => Str::uuid(),
            'user_id' => $this->student->id,
            'evaluation_id' => $this->evaluation->id,
            'score' => 18.5,
            'max_score' => 20,
            'correct_answers' => 14,
            'total_questions' => 15,
            'time_taken' => 2400,
            'status' => 'completed',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'attempt_number' => 1,
        ]);
    }

    public function test_teacher_can_get_performance_report(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/reports/performance");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_evaluations',
                    'average_score',
                    'total_students',
                    'passing_rate',
                    'top_performers',
                    'difficulty_areas',
                ],
            ]);
    }

    public function test_teacher_can_get_grades_report(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/reports/grades");

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'total'],
                'summary' => ['average', 'max', 'min', 'total'],
            ]);
    }

    public function test_student_cannot_access_reports(): void
    {
        Sanctum::actingAs($this->student, ['web']);

        $response = $this->getJson("{$this->baseUrl}/reports/performance");

        $response->assertStatus(403);

        $response = $this->getJson("{$this->baseUrl}/reports/grades");

        $response->assertStatus(403);

        $response = $this->getJson("{$this->baseUrl}/reports/student/{$this->student->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_reports(): void
    {
        $response = $this->getJson("{$this->baseUrl}/reports/performance");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access performance report');

        $response = $this->getJson("{$this->baseUrl}/reports/grades");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access grades report');

        $response = $this->getJson("{$this->baseUrl}/reports/student/{$this->student->id}");
        $this->assertTrue($response->status() >= 400, 'Unauthenticated user should not access student report');
    }

    public function test_teacher_can_get_student_report(): void
    {
        Sanctum::actingAs($this->teacher, ['web']);

        $response = $this->getJson("{$this->baseUrl}/reports/student/{$this->student->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'student' => ['id', 'full_name', 'email'],
                'stats' => [
                    'total_lessons_completed',
                    'total_lessons_in_progress',
                    'average_score',
                    'total_evaluations',
                    'best_score',
                    'worst_score',
                    'current_streak',
                    'badges',
                ],
                'lesson_progress',
                'evaluation_results',
                'strengths',
            ])
            ->assertJsonPath('student.id', $this->student->id->toString())
            ->assertJsonPath('student.email', $this->student->email);
    }

    public function test_admin_can_access_reports(): void
    {
        Sanctum::actingAs($this->admin, ['web']);

        $this->getJson("{$this->baseUrl}/reports/performance")->assertOk();
        $this->getJson("{$this->baseUrl}/reports/grades")->assertOk();
        $this->getJson("{$this->baseUrl}/reports/student/{$this->student->id}")->assertOk();
    }
}
