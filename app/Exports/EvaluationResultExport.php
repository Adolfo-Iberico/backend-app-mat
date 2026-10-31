<?php

namespace App\Exports;

use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\InstitutionConfig;
use App\Models\User;
use App\Support\EvaluationAnswerFormatter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EvaluationResultExport implements FromCollection, WithHeadings
{
    public function __construct(
        private Evaluation $evaluation,
        private EvaluationResult $result,
        private User $student
    ) {}

    public function collection(): Collection
    {
        $institution = InstitutionConfig::first()?->institution_name ?? $this->student->institution ?? '';
        $teacherName = $this->evaluation->lesson?->course?->teacher?->full_name
            ?? $this->evaluation->teacher?->full_name
            ?? '';
        $course = $this->evaluation->lesson?->course;
        $schoolClass = $course?->salon?->display_name ?? '';

        return $this->result->studentAnswers->map(fn ($answer) => [
            $this->evaluation->title,
            $this->student->full_name,
            $teacherName,
            $course?->name ?? $this->evaluation->lesson?->title ?? '',
            $schoolClass,
            $institution,
            $this->result->score,
            $this->result->correct_answers,
            $this->result->total_questions,
            $this->result->started_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i'),
            $this->result->completed_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i'),
            $this->formatDuration($this->result->time_taken),
            $this->result->attempt_number,
            $answer->question?->question_text ?? '',
            $answer->question ? EvaluationAnswerFormatter::submittedAnswer($answer->question, $answer->answer) : $answer->answer,
            $answer->question ? EvaluationAnswerFormatter::correctAnswer($answer->question) : '',
            $answer->is_correct
                ? 'Correcta'
                : ((float) $answer->points_earned > 0 ? 'Parcialmente correcta' : 'Incorrecta'),
            number_format((float) $answer->points_earned, 2, '.', ''),
        ]);
    }

    public function headings(): array
    {
        return [
            'Evaluación',
            'Estudiante',
            'Docente',
            'Curso',
            'Salón',
            'Colegio',
            'Puntaje (/20)',
            'Respuestas correctas',
            'Total de preguntas',
            'Inicio',
            'Fin',
            'Duración',
            'Intento',
            'Pregunta',
            'Respuesta del estudiante',
            'Respuesta correcta',
            'Resultado',
            'Puntos obtenidos',
        ];
    }

    private function formatDuration(?int $seconds): string
    {
        $seconds = max(0, (int) $seconds);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
