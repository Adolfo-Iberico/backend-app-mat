<?php

namespace Database\Seeders;

use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\SubmittedWork;
use Illuminate\Database\Seeder;

/**
 * Genera entregas calificadas para trabajos completados que alimentan los rankings.
 */
class RankingSeeder extends Seeder
{
    /**
     * Crea registros faltantes a partir del progreso, resultados y exámenes finalizados.
     */
    public function run(): void
    {
        $created = 0;

        $completedLessons = LessonProgress::where('status', 'completed')->get();

        foreach ($completedLessons as $lp) {
            $exists = SubmittedWork::where('student_id', $lp->user_id)
                ->where('lesson_id', $lp->lesson_id)
                ->where('work_type', 'lesson')
                ->exists();

            if (! $exists) {
                $lesson = Lesson::find($lp->lesson_id);
                SubmittedWork::create([
                    'student_id' => $lp->user_id,
                    'lesson_id' => $lp->lesson_id,
                    'work_type' => 'lesson',
                    'title' => $lesson?->title ?? 'Lesson Work',
                    'status' => 'graded',
                    'score' => rand(10, 20),
                    'max_score' => 20,
                    'submitted_at' => $lp->completed_at ?? $lp->updated_at,
                    'graded_at' => $lp->completed_at ?? $lp->updated_at,
                ]);
                $created++;
            }
        }

        // Resultados de evaluaciones (excluyendo exámenes, que generan su propio tipo).
        $examIds = Exam::pluck('id');

        $evalResults = EvaluationResult::where('status', 'completed')
            ->whereNotIn('evaluation_id', $examIds)
            ->get();

        foreach ($evalResults as $er) {
            $exists = SubmittedWork::where('student_id', $er->user_id)
                ->where('evaluation_id', $er->evaluation_id)
                ->where('work_type', 'evaluation')
                ->exists();

            if (! $exists) {
                $evaluation = Evaluation::find($er->evaluation_id);
                $scaledScore = $er->max_score > 0 ? (int) round(($er->score / $er->max_score) * 20) : rand(10, 20);
                SubmittedWork::create([
                    'student_id' => $er->user_id,
                    'evaluation_id' => $er->evaluation_id,
                    'work_type' => 'evaluation',
                    'title' => $evaluation?->title ?? 'Evaluation Work',
                    'status' => 'graded',
                    'score' => min($scaledScore, 20),
                    'max_score' => 20,
                    'submitted_at' => $er->completed_at,
                    'graded_at' => $er->completed_at,
                ]);
                $created++;
            }
        }

        // Intentos de exámenes (evaluation_results de evaluaciones type='exam').
        $examAttempts = EvaluationResult::where('status', 'completed')
            ->whereIn('evaluation_id', $examIds)
            ->get();

        foreach ($examAttempts as $ea) {
            $exists = SubmittedWork::where('student_id', $ea->user_id)
                ->where('evaluation_id', $ea->evaluation_id)
                ->where('work_type', 'exam')
                ->exists();

            if (! $exists) {
                $exam = Exam::find($ea->evaluation_id);
                $scaledScore = $ea->max_score > 0 ? (int) round(($ea->score / $ea->max_score) * 20) : rand(10, 20);
                SubmittedWork::create([
                    'student_id' => $ea->user_id,
                    'evaluation_id' => $ea->evaluation_id,
                    'work_type' => 'exam',
                    'title' => $exam?->title ?? 'Exam Work',
                    'status' => 'graded',
                    'score' => min($scaledScore, 20),
                    'max_score' => 20,
                    'submitted_at' => $ea->completed_at,
                    'graded_at' => $ea->completed_at,
                ]);
                $created++;
            }
        }

        $this->command->info("RankingSeeder: Created {$created} submitted work records.");
    }
}
