<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Trabajos entregados</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.meta { color: #6b7280; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #004AC6; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8f9ff; }
    </style>
</head>
<body>
    <h1>Trabajos entregados</h1>
    <p class="meta">Generado el {{ now()->format('d/m/Y H:i') }} — {{ $works->count() }} registros</p>

    <table>
        <thead>
            <tr>
                <th>Estudiante</th>
                <th>Tipo</th>
                <th>Título</th>
                <th>Estado</th>
                <th>Nota</th>
                <th>Docente</th>
                <th>Enviado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($works as $work)
            <tr>
                <td>{{ $work->student?->full_name ?? '—' }}</td>
                <td>{{ $work->work_type }}</td>
                <td>{{ $work->title }}</td>
                <td>{{ $work->status }}</td>
                <td>{{ $work->score !== null ? $work->score . '/' . $work->max_score : '—' }}</td>
                <td>{{ $work->evaluation?->teacher?->full_name ?? $work->lesson?->teacher?->full_name ?? '—' }}</td>
                <td>{{ $work->submitted_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
