<?php

namespace App\Services;

use App\Exports\StudentProgressExport;
use App\Exports\StudentProgressPDFExport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Genera el informe de progreso de un estudiante en PDF, Excel o CSV.
 * Compartido por docentes, el propio estudiante y la consulta pública por DNI.
 */
class StudentReportService
{
    /** Construye el HTML del informe con los datos del estudiante. */
    public function html(array $studentData): string
    {
        $student = $studentData['student'];
        $stats = $studentData['stats'];

        $lessonRows = '';
        foreach ($studentData['lessons'] as $lesson) {
            $statusColor = $lesson->status === 'completed' ? '#16a34a' : ($lesson->status === 'in_progress' ? '#d97706' : '#6b7280');
            $lessonRows .= '<tr>';
            $lessonRows .= '<td>'.htmlspecialchars($lesson->title).'</td>';
            $lessonRows .= '<td>'.htmlspecialchars($lesson->unit ?? 'N/A').'</td>';
            $lessonRows .= '<td><span style="color:'.$statusColor.';font-weight:bold;">'.ucfirst($lesson->status).'</span></td>';
            $lessonRows .= '<td>'.($lesson->progress_percentage ?? 0).'%</td>';
            $lessonRows .= '<td>'.($lesson->updated_at ? $lesson->updated_at->format('d/m/Y') : 'N/A').'</td>';
            $lessonRows .= '</tr>';
        }

        $evalRows = '';
        foreach ($studentData['evaluations'] as $eval) {
            $evalRows .= '<tr>';
            $evalRows .= '<td>'.htmlspecialchars($eval->title).'</td>';
            $evalRows .= '<td>'.number_format((float) $eval->score, 1).'</td>';
            $evalRows .= '<td>'.$eval->correct_answers.'/'.$eval->total_questions.'</td>';
            $evalRows .= '<td>'.($eval->created_at ? $eval->created_at->format('d/m/Y H:i') : 'N/A').'</td>';
            $evalRows .= '</tr>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #2563eb; padding-bottom: 15px; }
            .header h1 { color: #2563eb; margin: 0; font-size: 22px; }
            .header p { color: #666; margin: 5px 0 0; font-size: 11px; }
            .student-info { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px; margin-bottom: 20px; }
            .student-info h2 { margin: 0 0 5px; color: #1e40af; font-size: 14px; }
            .stats-grid { display: flex; gap: 10px; margin-bottom: 20px; }
            .stat-box { flex: 1; background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px; text-align: center; }
            .stat-box .value { font-size: 18px; font-weight: bold; color: #1e40af; }
            .stat-box .label { font-size: 9px; color: #666; margin-top: 3px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #2563eb; color: #fff; padding: 8px 6px; text-align: left; font-size: 9px; }
            td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
            tr:nth-child(even) { background: #f9fafb; }
            .section-title { color: #2563eb; font-size: 13px; margin: 20px 0 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        </style></head>
        <body>
            <div class="header">
                <h1>Aulamate - Reporte del Estudiante</h1>
                <p>Generado: '.now()->format('d/m/Y H:i').'</p>
            </div>
            <div class="student-info">
                <h2>'.htmlspecialchars($student->full_name ?? 'N/A').'</h2>
                <p>Email: '.htmlspecialchars($student->email ?? 'N/A').'</p>
            </div>
            <div class="stats-grid">
                <div class="stat-box"><div class="value">'.$stats['lessons_completed'].'</div><div class="label">Lecciones Completadas</div></div>
                <div class="stat-box"><div class="value">'.$stats['lessons_in_progress'].'</div><div class="label">En Progreso</div></div>
                <div class="stat-box"><div class="value">'.number_format((float) $stats['average_score'], 1).'</div><div class="label">Promedio</div></div>
                <div class="stat-box"><div class="value">'.$stats['total_evaluations'].'</div><div class="label">Evaluaciones</div></div>
            </div>
            <div class="section-title">Progreso en Lecciones</div>
            <table>
                <thead><tr><th>Lección</th><th>Unidad</th><th>Estado</th><th>Progreso</th><th>Última Actualización</th></tr></thead>
                <tbody>'.$lessonRows.'</tbody>
            </table>
            <div class="section-title">Historial de Evaluaciones</div>
            <table>
                <thead><tr><th>Evaluación</th><th>Puntaje</th><th>Correctas/Total</th><th>Fecha</th></tr></thead>
                <tbody>'.$evalRows.'</tbody>
            </table>
        </body></html>';
    }

    /** Descarga el informe del estudiante en PDF. */
    public function pdf(string $studentId, ?string $filename = null)
    {
        $studentData = (new StudentProgressPDFExport($studentId))->getData();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($studentData));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Dompdf nativo no tiene download(); se devuelve el PDF como respuesta
        // de descarga con las cabeceras correctas.
        $nombre = $filename ?? ('reporte-estudiante-'.$studentId.'.pdf');

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /** Descarga el informe del estudiante en Excel. */
    public function excel(string $studentId, ?string $filename = null)
    {
        $studentData = (new StudentProgressPDFExport($studentId))->getData();

        return Excel::download(
            new StudentProgressExport($studentData),
            $filename ?? ('reporte-estudiante-'.$studentId.'.xlsx')
        );
    }

    /** Transmite el informe del estudiante en CSV. */
    public function csv(string $studentId, ?string $filename = null)
    {
        $data = (new StudentProgressPDFExport($studentId))->getData();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.($filename ?? ('reporte-estudiante-'.$studentId.'.csv')).'"',
        ];

        $callback = function () use ($data, $studentId) {
            $handle = fopen('php://output', 'w');
            $studentName = $data['student'] ? ($data['student']->full_name ?? 'Estudiante') : 'Estudiante';
            fputcsv($handle, [$this->sanitizeCsvValue($studentName), $studentId]);
            fputcsv($handle, []);
            fputcsv($handle, [__('csv_header_evaluation'), __('csv_header_type'), __('csv_header_score'), __('csv_header_date')]);

            foreach (($data['results'] ?? []) as $row) {
                $row = (array) $row;
                fputcsv($handle, [
                    $this->sanitizeCsvValue($row['evaluation_name'] ?? ''),
                    $this->sanitizeCsvValue($row['type'] ?? ''),
                    $row['score'] ?? '',
                    $row['completed_at'] ?? '',
                ]);
            }

            if (isset($data['average'])) {
                fputcsv($handle, []);
                fputcsv($handle, [__('csv_header_average'), $data['average']]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** Escapa fórmulas CSV para evitar inyección de comandos de hoja de cálculo. */
    private function sanitizeCsvValue(string $value): string
    {
        $value = (string) $value;
        if (str_starts_with($value, ['=', '+', '-', '@'])) {
            return "'".$value;
        }

        return $value;
    }
}
