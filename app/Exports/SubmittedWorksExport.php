<?php

namespace App\Exports;

use App\Models\SubmittedWork;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Export de trabajos entregados para el panel de administración. */
class SubmittedWorksExport implements FromCollection, WithHeadings
{
    public function __construct(
        private Collection $works
    ) {}

    public function collection(): Collection
    {
        return $this->works->map(fn (SubmittedWork $work) => [
            $work->student?->full_name ?? '',
            $work->work_type,
            $work->title,
            $work->status,
            $work->score,
            $work->max_score,
            $work->teacher_feedback ?? '',
            $work->evaluation?->teacher?->full_name ?? $work->lesson?->teacher?->full_name ?? '',
            $work->submitted_at?->format('d/m/Y H:i') ?? '',
            $work->graded_at?->format('d/m/Y H:i') ?? '',
        ]);
    }

    public function headings(): array
    {
        return [
            'Estudiante',
            'Tipo',
            'Título',
            'Estado',
            'Nota',
            'Nota máxima',
            'Feedback docente',
            'Docente',
            'Enviado',
            'Calificado',
        ];
    }
}
