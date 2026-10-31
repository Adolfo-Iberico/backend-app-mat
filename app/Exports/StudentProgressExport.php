<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Genera una hoja con las evaluaciones y el progreso de lecciones de un estudiante. */
class StudentProgressExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private array $studentData;

    /** Recibe los conjuntos de evaluaciones y lecciones del estudiante. */
    public function __construct(array $studentData)
    {
        $this->studentData = $studentData;
    }

    /** Combina evaluaciones y lecciones en filas uniformes para exportar. */
    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->studentData['evaluations'] as $eval) {
            $rows->push([
                'type' => 'evaluacion',
                'title' => $eval->title,
                'score' => $eval->score,
                'correct' => $eval->correct_answers,
                'total' => $eval->total_questions,
                'date' => $eval->created_at?->format('d/m/Y H:i') ?? 'N/A',
            ]);
        }

        foreach ($this->studentData['lessons'] as $lesson) {
            $rows->push([
                'type' => 'leccion',
                'title' => $lesson->title,
                'score' => $lesson->progress_percentage ?? 0,
                'correct' => $lesson->status,
                'total' => '',
                'date' => $lesson->updated_at?->format('d/m/Y') ?? 'N/A',
            ]);
        }

        return $rows;
    }

    /** Devuelve los encabezados de las columnas exportadas. */
    public function headings(): array
    {
        return ['Tipo', 'Nombre', 'Puntaje / Progreso', 'Estado / Correctas', 'Total', 'Fecha'];
    }

    /** Presenta cada fila uniforme con etiquetas y valores legibles. */
    public function map($row): array
    {
        return [
            $row['type'] === 'evaluacion' ? 'Evaluación' : 'Lección',
            $row['title'],
            $row['type'] === 'evaluacion' ? $row['score'] : $row['score'].'%',
            $row['type'] === 'evaluacion' ? $row['correct'].'/'.$row['total'] : $row['correct'],
            $row['total'],
            $row['date'],
        ];
    }

    /** Aplica formato de negrita a la fila de encabezados. */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
