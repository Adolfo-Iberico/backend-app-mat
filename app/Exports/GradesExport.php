<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Prepara resultados de evaluaciones como una hoja de cálculo localizada. */
class GradesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private const HEADINGS = [
        'es' => ['Estudiante', 'Evaluación', 'Lección', 'Área', 'Puntaje', 'Calificación', 'Correctas/Total', 'Fecha'],
        'en' => ['Student', 'Evaluation', 'Lesson', 'Area', 'Score', 'Grade', 'Correct/Total', 'Date'],
        'qu' => ['Yachaykuna', 'Wanuchiy', 'Lección', 'Área', 'Puntaje', 'Yupay', 'Chiqap tukuy', 'P\'unchay'],
    ];

    private const STATUS = [
        'es' => ['completed' => 'Completado', 'pending' => 'Pendiente'],
        'en' => ['completed' => 'Completed', 'pending' => 'Pending'],
        'qu' => ['completed' => 'Tukuy', 'pending' => 'Wanalla'],
    ];

    /** Recibe las filas y el idioma de los encabezados y estados. */
    public function __construct(
        private Collection $rows,
        private string $lang = 'es'
    ) {}

    /** Devuelve las filas que procesará la exportación. */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /** Encabezados de la hoja para el idioma elegido, con español como respaldo. */
    public function headings(): array
    {
        return self::HEADINGS[$this->lang] ?? self->HEADINGS['es'];
    }

    /** Convierte un resultado en columnas de exportación y añade su estado si existe. */
    public function map($row): array
    {
        $status = self::STATUS[$this->lang] ?? self::STATUS['es'];

        $studentName = $row->student_name ?? ($row->user->full_name ?? 'N/A');
        $evalTitle = $row->evaluation_title ?? ($row->evaluation->title ?? 'N/A');
        $lessonTitle = $row->lesson_title ?? 'N/A';
        $area = $row->area ?? 'N/A';
        $score = $row->score ?? 0;
        $correct = $row->correct_answers ?? 0;
        $total = $row->total_questions ?? 0;
        $date = $row->completed_at ?? ($row->created_at?->format('d/m/Y H:i') ?? 'N/A');
        $rowStatus = $row->status ? ($status[$row->status] ?? $row->status) : '';

        $result = [
            $studentName,
            $evalTitle,
            $lessonTitle,
            $area,
            $score,
            GradesPDFExport::gradeLetter($score),
            $correct.'/'.$total,
            is_string($date) ? $date : $date->format('d/m/Y H:i'),
        ];

        if ($rowStatus) {
            $result[] = $rowStatus;
        }

        return $result;
    }

    /** Aplica formato de negrita a la fila de encabezados. */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
