<?php

namespace Database\Seeders;

use App\Models\Evaluation;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importa el catálogo de lecciones UNSAAC y crea una práctica autocorregible por lección.
 */
class UnsaacLessonSeeder extends Seeder
{
    public function run(): void
    {
        $catalogPath = database_path('seeders/data/unsaac_lessons.json');
        $catalog = json_decode(file_get_contents($catalogPath), true, flags: JSON_THROW_ON_ERROR);
        $teacher = User::where('email', 'profesor.math@mathflow.com')->first();

        if (! $teacher) {
            throw new RuntimeException('No existe el docente profesor.math@mathflow.com. Ejecuta MathContentSeeder primero.');
        }

        $orders = [];
        $created = 0;
        $updated = 0;

        foreach ($catalog as $item) {
            $pdfPath = public_path('materiales/lecciones/'.$item['pdf']);
            if (! is_file($pdfPath)) {
                throw new RuntimeException("No se encontró el PDF de la lección {$item['title']}: {$pdfPath}");
            }

            $key = $item['subject'].'|'.$item['grade'];
            $order = ($orders[$key] ?? 0) + 1;
            $orders[$key] = $order;

            DB::transaction(function () use ($item, $teacher, $order, &$created, &$updated): void {
                $lesson = Lesson::withTrashed()
                    ->where('title', $item['title'])
                    ->where('unit', $item['subject'])
                    ->where('grade', $item['grade'])
                    ->first();

                if ($lesson?->trashed()) {
                    return;
                }

                if (! $lesson) {
                    $lesson = Lesson::create([
                        'id' => (string) Str::uuid(),
                        'title' => $item['title'],
                        'description' => $this->description($item),
                        'content' => $this->content($item),
                        'teacher_id' => $teacher->id,
                        'grade' => $item['grade'],
                        'unit' => $item['subject'],
                        'topic' => $item['title'],
                        'difficulty' => $item['difficulty'],
                        'tags' => ['UNSAAC', $item['subject'], $item['grade']],
                        'estimated_time' => $item['difficulty'] === 'advanced' ? 60 : 45,
                        'is_published' => true,
                        'published_at' => now(),
                        'resources' => $this->resources($item),
                        'order' => $order,
                    ]);
                    $created++;
                } else {
                    $lesson->update([
                        'resources' => $this->mergeResources($lesson->resources ?? [], $this->resources($item)),
                    ]);
                    $updated++;
                }

                $evaluationTitle = "Práctica: {$item['title']} ({$item['grade']})";
                $evaluation = Evaluation::withTrashed()
                    ->where('lesson_id', $lesson->id)
                    ->where('title', $evaluationTitle)
                    ->first();

                if ($evaluation?->trashed()) {
                    return;
                }

                if (! $evaluation) {
                    $questions = $this->questionsFor($item['subject'], $item['title']);
                    $evaluation = Evaluation::create([
                        'id' => (string) Str::uuid(),
                        'title' => $evaluationTitle,
                        'description' => "Evaluación de práctica complementaria para {$item['title']}. Repasa la guía PDF y los recursos adjuntos antes de responder.",
                        'teacher_id' => $teacher->id,
                        'lesson_id' => $lesson->id,
                        'unit' => $item['subject'],
                        'type' => Evaluation::TYPE_PRACTICE,
                        'difficulty' => $item['difficulty'],
                        'time_limit' => 20,
                        'is_published' => true,
                        'auto_correct' => true,
                        'randomize_questions' => false,
                        'max_attempts' => 3,
                        'published_at' => now(),
                        'total_questions' => count($questions),
                        'total_points' => count($questions),
                    ]);

                    foreach ($questions as $questionOrder => $question) {
                        Question::create([
                            'id' => (string) Str::uuid(),
                            'evaluation_id' => $evaluation->id,
                            'type' => Question::TYPE_MULTIPLE_CHOICE,
                            'question_text' => $question['question_text'],
                            'options' => $question['options'],
                            'correct_answer' => $question['correct_answer'],
                            'explanation' => $question['explanation'],
                            'points' => 1,
                            'order' => $questionOrder,
                        ]);
                    }
                } elseif (! $evaluation->questions()->exists()) {
                    $questions = $this->questionsFor($item['subject'], $item['title']);

                    foreach ($questions as $questionOrder => $question) {
                        Question::create([
                            'id' => (string) Str::uuid(),
                            'evaluation_id' => $evaluation->id,
                            'type' => Question::TYPE_MULTIPLE_CHOICE,
                            'question_text' => $question['question_text'],
                            'options' => $question['options'],
                            'correct_answer' => $question['correct_answer'],
                            'explanation' => $question['explanation'],
                            'points' => 1,
                            'order' => $questionOrder,
                        ]);
                    }

                    $evaluation->update([
                        'total_questions' => count($questions),
                        'total_points' => count($questions),
                    ]);
                }
            });
        }

        $this->command?->info("Catálogo UNSAAC procesado: {$created} lecciones nuevas y {$updated} lecciones actualizadas.");
    }

    private function description(array $item): string
    {
        $difficulty = match ($item['difficulty']) {
            'basic' => 'básico',
            'advanced' => 'avanzado',
            default => 'intermedio',
        };

        return "Lección de {$item['subject']} para {$item['grade']} grado, nivel {$difficulty}. Incluye guía PDF, video de apoyo y práctica en Khan Academy.";
    }

    private function content(array $item): string
    {
        $title = e($item['title']);
        $subject = e($item['subject']);
        $grade = e($item['grade']);

        return "<h2>{$title}</h2>"
            ."<p>Esta lección de {$subject} está dirigida a estudiantes de {$grade}.</p>"
            .'<p>Estudia la guía PDF adjunta, revisa el video de apoyo y practica el tema en Khan Academy.</p>'
            .'<p>Al terminar, responde la evaluación de práctica para comprobar lo aprendido. Puedes repetirla hasta tres veces.</p>';
    }

    private function resources(array $item): array
    {
        $resources = [
            $this->resource(
                'pdf',
                '/materiales/lecciones/'.rawurlencode($item['pdf']),
                'Guía PDF: '.$item['title']
            ),
        ];

        if ($item['video_url']) {
            $resources[] = $this->resource(
                'video',
                $item['video_url'],
                $item['video_title'] ?: 'Video: '.$item['title']
            );
        } else {
            $query = http_build_query(['search_query' => $item['title'].' '.$item['subject'].' matemáticas']);
            $resources[] = $this->resource(
                'link',
                'https://www.youtube.com/results?'.$query,
                'Buscar videos sobre '.$item['title'].' en YouTube'
            );
        }

        foreach ($item['khan_links'] as $link) {
            $resources[] = $this->resource(
                'link',
                $link['url'],
                $link['title'] ?: 'Práctica en Khan Academy'
            );
        }

        return $resources;
    }

    private function resource(string $type, string $url, string $title): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'url' => $url,
            'title' => $title,
            'created_at' => now()->toISOString(),
        ];
    }

    private function mergeResources(array $existing, array $new): array
    {
        $knownUrls = array_column($existing, 'url');

        foreach ($new as $resource) {
            foreach ($existing as $index => $current) {
                if (($current['type'] ?? null) === $resource['type'] && ($current['title'] ?? null) === $resource['title']) {
                    if ($resource['type'] === 'pdf') {
                        $existing[$index]['url'] = $resource['url'];
                    }

                    continue 2;
                }
            }

            if (! in_array($resource['url'], $knownUrls, true)) {
                $existing[] = $resource;
                $knownUrls[] = $resource['url'];
            }
        }

        return $existing;
    }

    private function questionsFor(string $subject, string $title): array
    {
        $topic = Str::ascii(Str::lower($title));

        $topicQuestion = match ($subject) {
            'Álgebra' => $this->algebraQuestion($topic),
            'Aritmética' => $this->arithmeticQuestion($topic),
            'Geometría' => $this->geometryQuestion($topic),
            'Trigonometría' => $this->trigonometryQuestion($topic),
            default => $this->question(
                'Al resolver un problema matemático, ¿qué conviene identificar primero?',
                'Los datos, las incógnitas y las relaciones entre ellos',
                ['Solo el resultado final', 'Una operación al azar', 'Únicamente las unidades'],
                'Identificar los datos y las relaciones ayuda a elegir un procedimiento adecuado.'
            ),
        };

        $review = $this->reviewQuestions($subject);
        $offset = crc32($title) % count($review);
        $questions = [$topicQuestion];

        for ($index = 0; $index < 4; $index++) {
            $questions[] = $review[($offset + $index) % count($review)];
        }

        return $questions;
    }

    private function algebraQuestion(string $topic): array
    {
        if (Str::contains($topic, ['producto notable', 'productos notables'])) {
            return $this->question('(x + 2)² es igual a:', 'x² + 4x + 4', ['x² + 4', 'x² + 2x + 4', 'x² - 4x + 4'], 'Se aplica (a + b)² = a² + 2ab + b².');
        }
        if (Str::contains($topic, ['factorizacion', 'factorizar'])) {
            return $this->question('¿Cuál es la factorización de x² - 25?', '(x - 5)(x + 5)', ['(x - 25)(x + 1)', '(x - 5)²', '(x + 5)²'], 'Es una diferencia de cuadrados: a² - b² = (a - b)(a + b).');
        }
        if (Str::contains($topic, ['inecuacion', 'desigualdad'])) {
            return $this->question('Resuelve 2x + 1 > 7:', 'x > 3', ['x < 3', 'x > 4', 'x < 4'], 'Restando 1 queda 2x > 6; al dividir entre 2, x > 3.');
        }
        if (Str::contains($topic, ['cuadratica', 'segundo grado'])) {
            return $this->question('Las soluciones de x² - 5x + 6 = 0 son:', 'x = 2 y x = 3', ['x = -2 y x = -3', 'x = 1 y x = 6', 'x = 0 y x = 6'], 'Se factoriza como (x - 2)(x - 3) = 0.');
        }
        if (Str::contains($topic, ['sistema'])) {
            return $this->question('Resuelve el sistema x + y = 9, x - y = 3:', 'x = 6, y = 3', ['x = 3, y = 6', 'x = 9, y = 3', 'x = 5, y = 4'], 'Al sumar las ecuaciones se obtiene 2x = 12; entonces x = 6 e y = 3.');
        }
        if (Str::contains($topic, ['exponente', 'potenciacion', 'radical'])) {
            return $this->question('Usando las leyes de exponentes, 2³ · 2² es:', '32', ['16', '64', '12'], 'Al multiplicar potencias de igual base se suman exponentes: 2⁵ = 32.');
        }
        if (Str::contains($topic, ['polinomio'])) {
            return $this->question('¿Cuál es el grado del polinomio 3x⁴ - 2x + 1?', '4', ['3', '2', '1'], 'El grado de un polinomio es el mayor exponente de la variable.');
        }
        if (Str::contains($topic, ['funcion', 'progresion', 'sucesion'])) {
            return $this->question('En la sucesión 2, 5, 8, 11, ¿cuál es el siguiente término?', '14', ['13', '15', '16'], 'La sucesión aumenta de 3 en 3.');
        }
        if (Str::contains($topic, ['matriz', 'determinante'])) {
            return $this->question('¿Cuál es el determinante de [[2, 1], [3, 4]]?', '5', ['11', '8', '2'], 'El determinante es 2·4 - 1·3 = 5.');
        }
        if (Str::contains($topic, ['division de polinomios', 'ruffini', 'horner'])) {
            return $this->question('Al dividir x² - 1 entre x - 1, el cociente es:', 'x + 1', ['x - 1', 'x² + 1', 'x'], 'x² - 1 = (x - 1)(x + 1).');
        }
        if (Str::contains($topic, ['racionalizacion'])) {
            return $this->question('Racionaliza 1/√5:', '√5/5', ['5√5', '1/5', '√5'], 'Multiplicando numerador y denominador por √5 se obtiene √5/5.');
        }
        if (Str::contains($topic, ['lenguaje algebraico'])) {
            return $this->question('La expresión “el triple de un número más cinco” se representa como:', '3x + 5', ['3(x + 5)', 'x + 15', '5x + 3'], 'El triple de x es 3x; luego se suma 5.');
        }

        return $this->question('Resuelve 4x - 8 = 12:', 'x = 5', ['x = 4', 'x = 6', 'x = 8'], 'Sumando 8 queda 4x = 20; al dividir entre 4, x = 5.');
    }

    private function arithmeticQuestion(string $topic): array
    {
        if (Str::contains($topic, ['por ciento', 'porcentaje'])) {
            return $this->question('¿Cuánto es el 20 % de 150?', '30', ['20', '25', '35'], '20 % = 0,20 y 0,20 × 150 = 30.');
        }
        if (Str::contains($topic, ['fraccion'])) {
            return $this->question('¿Cuánto es 1/2 + 1/3?', '5/6', ['2/5', '1/5', '2/6'], 'Con denominador común 6: 3/6 + 2/6 = 5/6.');
        }
        if (Str::contains($topic, ['mcd', 'maximo comun divisor'])) {
            return $this->question('¿Cuál es el MCD de 18 y 24?', '6', ['3', '8', '12'], 'El mayor divisor común de 18 y 24 es 6.');
        }
        if (Str::contains($topic, ['mcm', 'minimo comun multiplo'])) {
            return $this->question('¿Cuál es el mcm de 6 y 8?', '24', ['12', '36', '48'], 'El menor múltiplo común positivo de 6 y 8 es 24.');
        }
        if (Str::contains($topic, ['proporcion', 'regla de tres', 'reparto proporcional'])) {
            return $this->question('Si 3 cuadernos cuestan 12 soles, ¿cuánto cuestan 5 al mismo precio unitario?', '20 soles', ['15 soles', '18 soles', '24 soles'], 'Cada cuaderno cuesta 4 soles; cinco cuestan 5 × 4 = 20.');
        }
        if (Str::contains($topic, ['promedio', 'media aritmetica'])) {
            return $this->question('¿Cuál es el promedio de 4, 6 y 8?', '6', ['5', '7', '18'], 'El promedio es (4 + 6 + 8) / 3 = 6.');
        }
        if (Str::contains($topic, ['interes simple'])) {
            return $this->question('Un capital de 1000 soles gana 10 % anual simple durante 2 años. ¿Cuál es el interés?', '200 soles', ['100 soles', '1100 soles', '220 soles'], 'I = C × r × t = 1000 × 0,10 × 2 = 200.');
        }
        if (Str::contains($topic, ['interes compuesto'])) {
            return $this->question('¿Cuál es el monto de 1000 soles al 10 % anual compuesto durante 2 años?', '1210 soles', ['1200 soles', '1100 soles', '1220 soles'], 'M = 1000 × 1,1² = 1210.');
        }
        if (Str::contains($topic, ['probabilidad'])) {
            return $this->question('Al lanzar un dado justo, ¿cuál es la probabilidad de obtener un número par?', '1/2', ['1/3', '2/3', '1/6'], 'Hay tres resultados pares entre seis posibles: 3/6 = 1/2.');
        }
        if (Str::contains($topic, ['sistema de numeracion', 'conversion de bases'])) {
            return $this->question('¿A qué número decimal equivale 1011₂?', '11', ['9', '10', '13'], '1011₂ = 1·8 + 0·4 + 1·2 + 1·1 = 11.');
        }
        if (Str::contains($topic, ['jerarquia'])) {
            return $this->question('Calcula 3 + 2 × 4 respetando la jerarquía de operaciones:', '11', ['20', '14', '24'], 'Primero se multiplica: 2 × 4 = 8; luego 3 + 8 = 11.');
        }
        if (Str::contains($topic, ['valor absoluto'])) {
            return $this->question('¿Cuál es el valor absoluto de -7?', '7', ['-7', '0', '14'], 'El valor absoluto es la distancia al cero y no es negativa.');
        }
        if (Str::contains($topic, ['sucesion', 'sumatoria'])) {
            return $this->question('¿Cuál es la suma de los primeros 10 números naturales positivos?', '55', ['45', '50', '100'], 'La suma es 10 × 11 / 2 = 55.');
        }

        return $this->question('¿Cuánto es 48 ÷ 6?', '8', ['6', '7', '9'], '48 dividido entre 6 es 8.');
    }

    private function geometryQuestion(string $topic): array
    {
        if (Str::contains($topic, ['heron'])) {
            return $this->question('¿Cuál es el área de un triángulo de lados 3, 4 y 5?', '6 unidades cuadradas', ['12 unidades cuadradas', '7,5 unidades cuadradas', '10 unidades cuadradas'], 'Es un triángulo rectángulo con catetos 3 y 4: A = 3 × 4 / 2 = 6.');
        }
        if (Str::contains($topic, ['pitagoras', 'relaciones metricas'])) {
            return $this->question('Un triángulo rectángulo tiene catetos 6 y 8. ¿Cuánto mide la hipotenusa?', '10', ['12', '14', '8'], 'Por Pitágoras, c = √(6² + 8²) = √100 = 10.');
        }
        if (Str::contains($topic, ['triangulo'])) {
            return $this->question('Dos ángulos de un triángulo miden 50° y 60°. ¿Cuánto mide el tercero?', '70°', ['80°', '90°', '110°'], 'La suma de los ángulos interiores de un triángulo es 180°.');
        }
        if (Str::contains($topic, ['circunferencia', 'circulo', 'circular'])) {
            return $this->question('¿Cuál es el diámetro de un círculo de radio 7 cm?', '14 cm', ['7 cm', '21 cm', '49 cm'], 'El diámetro es el doble del radio: 2 × 7 = 14 cm.');
        }
        if (Str::contains($topic, ['area', 'perimetro'])) {
            return $this->question('¿Cuál es el área de un rectángulo de base 8 cm y altura 5 cm?', '40 cm²', ['26 cm²', '13 cm²', '35 cm²'], 'El área del rectángulo es base × altura = 8 × 5 = 40 cm².');
        }
        if (Str::contains($topic, ['poligono'])) {
            return $this->question('¿Cuál es la suma de los ángulos interiores de un pentágono?', '540°', ['360°', '720°', '900°'], 'La suma es (n - 2) × 180° = (5 - 2) × 180° = 540°.');
        }
        if (Str::contains($topic, ['solido', 'volumen'])) {
            return $this->question('¿Cuál es el volumen de un prisma rectangular de dimensiones 2, 3 y 4?', '24 unidades cúbicas', ['9 unidades cúbicas', '18 unidades cúbicas', '12 unidades cúbicas'], 'El volumen es largo × ancho × alto = 2 × 3 × 4 = 24.');
        }
        if (Str::contains($topic, ['bisectriz'])) {
            return $this->question('Una bisectriz divide un ángulo de 60° en dos ángulos de:', '30° cada uno', ['20° cada uno', '60° cada uno', '120° cada uno'], 'La bisectriz divide un ángulo en dos partes iguales.');
        }

        return $this->question('Dos ángulos de un triángulo miden 50° y 60°. ¿Cuánto mide el tercero?', '70°', ['80°', '90°', '110°'], 'La suma de los ángulos interiores de un triángulo es 180°.');
    }

    private function trigonometryQuestion(string $topic): array
    {
        if (Str::contains($topic, ['sistema de medicion', 'radian'])) {
            return $this->question('¿A cuántos radianes equivalen 180°?', 'π rad', ['π/2 rad', '2π rad', '3π/2 rad'], '180° × π/180° = π rad.');
        }
        if (Str::contains($topic, ['identidad'])) {
            return $this->question('¿Qué identidad trigonométrica es fundamental?', 'sen²(x) + cos²(x) = 1', ['sen(x) + cos(x) = 1', 'sen²(x) - cos²(x) = 1', 'tan(x) = sen(x) + cos(x)'], 'La identidad pitagórica establece sen²(x) + cos²(x) = 1.');
        }
        if (Str::contains($topic, ['senos y cosenos', 'ley de senos', 'ley de cosenos'])) {
            return $this->question('En un triángulo con lados 3 y 4 que forman un ángulo recto, ¿cuánto mide el lado opuesto?', '5', ['6', '7', '12'], 'Por la ley de cosenos o Pitágoras, el lado opuesto mide √(3² + 4²) = 5.');
        }
        if (Str::contains($topic, ['ecuacion'])) {
            return $this->question('En el intervalo 0° ≤ x ≤ 90°, si sen(x) = 1/2, entonces x es:', '30°', ['45°', '60°', '90°'], 'El seno de 30° es 1/2.');
        }
        if (Str::contains($topic, ['circunferencia trigonometrica'])) {
            return $this->question('En la circunferencia unitaria, el punto correspondiente a 0° es:', '(1, 0)', ['(0, 1)', '(-1, 0)', '(0, -1)'], 'Las coordenadas son (cos 0°, sen 0°) = (1, 0).');
        }
        if (Str::contains($topic, ['funcion trigonometrica', 'graficas', 'dominio y rango'])) {
            return $this->question('¿Cuál es la amplitud de la función y = 2 sen(x)?', '2', ['1', '0', '4'], 'El factor que multiplica al seno determina la amplitud: |2| = 2.');
        }
        if (Str::contains($topic, ['angulos compuestos', 'suma y diferencia'])) {
            return $this->question('¿Cuál es sen(30° + 30°)?', '√3/2', ['1/2', '1', '0'], '30° + 30° = 60° y sen(60°) = √3/2.');
        }
        if (Str::contains($topic, ['reduccion de angulos'])) {
            return $this->question('¿Cuánto vale sen(150°)?', '1/2', ['-1/2', '√3/2', '-√3/2'], '150° está en el segundo cuadrante y su ángulo de referencia es 30°.');
        }
        if (Str::contains($topic, ['triangulo'])) {
            return $this->question('En un triángulo rectángulo, si el cateto opuesto mide 3 y la hipotenusa 5, sen(θ) es:', '3/5', ['4/5', '5/3', '3/4'], 'sen(θ) = cateto opuesto / hipotenusa = 3/5.');
        }

        return $this->question('¿Cuánto vale sen(30°)?', '1/2', ['√3/2', '1', '0'], 'El valor notable de sen(30°) es 1/2.');
    }

    private function reviewQuestions(string $subject): array
    {
        return match ($subject) {
            'Álgebra' => [
                $this->question('Resuelve 2x + 6 = 14:', 'x = 4', ['x = 3', 'x = 5', 'x = 10'], 'Restando 6 queda 2x = 8; entonces x = 4.'),
                $this->question('¿Cuál es la factorización de x² - 16?', '(x - 4)(x + 4)', ['(x - 8)(x + 2)', '(x - 4)²', '(x + 4)²'], 'Es una diferencia de cuadrados.'),
                $this->question('¿Cuánto es 2³ × 2²?', '32', ['16', '64', '12'], 'Se suman exponentes de igual base: 2⁵ = 32.'),
                $this->question('Si f(x) = 3x - 1, ¿cuánto vale f(2)?', '5', ['4', '6', '7'], 'f(2) = 3 × 2 - 1 = 5.'),
                $this->question('Las raíces de x² - 4x + 3 = 0 son:', 'x = 1 y x = 3', ['x = -1 y x = -3', 'x = 2 y x = 2', 'x = 0 y x = 3'], 'Se factoriza como (x - 1)(x - 3) = 0.'),
                $this->question('¿Cuál es el grado de 5x³ + x - 2?', '3', ['2', '1', '5'], 'El mayor exponente de la variable es 3.'),
            ],
            'Aritmética' => [
                $this->question('¿Cuánto es 3/4 de 20?', '15', ['12', '16', '18'], '20 × 3/4 = 15.'),
                $this->question('¿Cuánto es el 15 % de 200?', '30', ['15', '20', '35'], '0,15 × 200 = 30.'),
                $this->question('¿Cuál es el mcm de 6 y 8?', '24', ['12', '36', '48'], 'El menor múltiplo común positivo es 24.'),
                $this->question('¿Cuál es el promedio de 4, 6 y 8?', '6', ['5', '7', '18'], '(4 + 6 + 8) / 3 = 6.'),
                $this->question('¿Cuánto es 2/3 + 1/6?', '5/6', ['3/9', '1/2', '4/6'], 'Con denominador común: 4/6 + 1/6 = 5/6.'),
                $this->question('Se reparten 40 objetos en razón 3:5. ¿Cuántos corresponden a la parte menor?', '15', ['8', '20', '25'], 'Hay 8 partes; cada una vale 5 y la parte menor tiene 3 × 5 = 15.'),
            ],
            'Geometría' => [
                $this->question('Dos ángulos de un triángulo miden 50° y 60°. El tercero mide:', '70°', ['80°', '90°', '110°'], 'La suma de los ángulos interiores es 180°.'),
                $this->question('Un triángulo rectángulo tiene catetos 6 y 8. Su hipotenusa mide:', '10', ['12', '14', '8'], '√(6² + 8²) = 10.'),
                $this->question('El área de un rectángulo de 8 cm por 5 cm es:', '40 cm²', ['26 cm²', '13 cm²', '35 cm²'], 'Área = base × altura = 40 cm².'),
                $this->question('La longitud de una circunferencia de radio 7 cm, usando π = 22/7, es:', '44 cm', ['22 cm', '49 cm', '154 cm'], 'C = 2πr = 2 × 22/7 × 7 = 44 cm.'),
                $this->question('La suma de ángulos interiores de un pentágono es:', '540°', ['360°', '720°', '900°'], '(5 - 2) × 180° = 540°.'),
                $this->question('El volumen de un prisma de dimensiones 2, 3 y 4 es:', '24 unidades cúbicas', ['9 unidades cúbicas', '18 unidades cúbicas', '12 unidades cúbicas'], 'V = 2 × 3 × 4 = 24.'),
            ],
            default => [
                $this->question('¿Cuánto vale sen(30°)?', '1/2', ['√3/2', '1', '0'], 'Es un valor trigonométrico notable.'),
                $this->question('¿Cuánto vale cos(60°)?', '1/2', ['√3/2', '1', '0'], 'Es un valor trigonométrico notable.'),
                $this->question('¿Cuánto vale tan(45°)?', '1', ['0', '1/2', '√3'], 'sen(45°) / cos(45°) = 1.'),
                $this->question('¿Cuál es la identidad pitagórica fundamental?', 'sen²(x) + cos²(x) = 1', ['sen(x) + cos(x) = 1', 'sen²(x) - cos²(x) = 1', 'tan(x) = sen(x) + cos(x)'], 'La identidad se cumple para todo ángulo x.'),
                $this->question('¿A cuántos radianes equivalen 180°?', 'π rad', ['π/2 rad', '2π rad', '3π/2 rad'], '180° equivale a π radianes.'),
                $this->question('Si sen(θ) = 3/5 y θ es agudo, cos(θ) es:', '4/5', ['3/5', '5/3', '5/4'], 'Por la identidad pitagórica, cos(θ) = √(1 - 9/25) = 4/5.'),
            ],
        };
    }

    private function question(
        string $prompt,
        string $correct,
        array $distractors,
        string $explanation
    ): array {
        return [
            'question_text' => $prompt,
            'options' => array_merge([$correct], $distractors),
            'correct_answer' => $correct,
            'explanation' => $explanation,
        ];
    }
}
