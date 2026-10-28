<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga exámenes de demostración y sus preguntas de opción múltiple.
 */
class ExamSeeder extends Seeder
{
    /**
     * Busca al docente de ejemplo y crea sus exámenes con preguntas asociadas.
     */
    public function run(): void
    {
        $teacher = User::where('email', 'profesor.math@mathflow.com')->first();

        if (! $teacher) {
            return;
        }

        $exams = [
            [
                'title' => 'Examen de Álgebra - Ecuaciones',
                'description' => 'Evaluación sobre ecuaciones lineales y cuadráticas. Incluye resolución de ecuaciones de primer y segundo grado.',
                'unit' => 'Álgebra',
                'difficulty' => 'basic',
                'time_limit' => 45,
                'max_attempts' => 2,
                'auto_correct' => true,
                'randomize_questions' => false,
                'is_active' => true,
                'is_published' => true,
                'published_at' => now()->subDays(3),
                'questions' => [
                    ['question_text' => 'Resuelve: 3x + 6 = 21', 'options' => ['x = 3', 'x = 5', 'x = 7', 'x = 9'], 'correct_answer' => '1', 'explanation' => 'Restamos 6: 3x = 15. Dividimos entre 3: x = 5.', 'points' => 2, 'order' => 0],
                    ['question_text' => '¿Cuál es la raíz de x² - 9 = 0?', 'options' => ['x = 3', 'x = -3', 'x = ±3', 'x = 9'], 'correct_answer' => '2', 'explanation' => 'Factorizando: (x-3)(x+3) = 0, entonces x = 3 o x = -3.', 'points' => 2, 'order' => 1],
                    ['question_text' => 'Si 2x - 4 = 10, ¿cuánto vale x?', 'options' => ['x = 5', 'x = 6', 'x = 7', 'x = 8'], 'correct_answer' => '2', 'explanation' => 'Sumamos 4: 2x = 14. Dividimos entre 2: x = 7.', 'points' => 2, 'order' => 2],
                    ['question_text' => 'Resuelve el sistema: x + y = 10, x - y = 4', 'options' => ['x = 7, y = 3', 'x = 6, y = 4', 'x = 5, y = 5', 'x = 8, y = 2'], 'correct_answer' => '0', 'explanation' => 'Sumando ambas ecuaciones: 2x = 14, x = 7. Luego y = 3.', 'points' => 3, 'order' => 3],
                    ['question_text' => '¿Cuál es la solución de 5x = 0?', 'options' => ['x = 0', 'x = 1', 'x = 5', 'No tiene solución'], 'correct_answer' => '0', 'explanation' => 'Dividiendo entre 5: x = 0.', 'points' => 1, 'order' => 4],
                ],
            ],
            [
                'title' => 'Examen de Geometría - Figuras y Áreas',
                'description' => 'Evaluación sobre figuras geométricas, perímetros y áreas de polígonos y círculos.',
                'unit' => 'Geometría',
                'difficulty' => 'intermediate',
                'time_limit' => 60,
                'max_attempts' => 1,
                'auto_correct' => true,
                'randomize_questions' => true,
                'is_active' => false,
                'is_published' => false,
                'published_at' => null,
                'questions' => [
                    ['question_text' => '¿Cuál es el área de un rectángulo de base 8 y altura 5?', 'options' => ['40', '26', '13', '35'], 'correct_answer' => '0', 'explanation' => 'Área = base × altura = 8 × 5 = 40.', 'points' => 2, 'order' => 0],
                    ['question_text' => '¿Cuánto mide la hipotenusa de un triángulo rectángulo con catetos 3 y 4?', 'options' => ['5', '6', '7', '12'], 'correct_answer' => '0', 'explanation' => 'Teorema de Pitágoras: √(3² + 4²) = √(9 + 16) = √25 = 5.', 'points' => 2, 'order' => 1],
                    ['question_text' => '¿Cuál es el perímetro de un cuadrado de lado 6 cm?', 'options' => ['12 cm', '18 cm', '24 cm', '36 cm'], 'correct_answer' => '2', 'explanation' => 'Perímetro = 4 × lado = 4 × 6 = 24 cm.', 'points' => 1, 'order' => 2],
                    ['question_text' => '¿Cuál es el área de un círculo con radio 7 cm? (Usar π ≈ 22/7)', 'options' => ['44 cm²', '154 cm²', '49 cm²', '88 cm²'], 'correct_answer' => '1', 'explanation' => 'Área = π × r² = (22/7) × 49 = 154 cm².', 'points' => 3, 'order' => 3],
                    ['question_text' => '¿Cuántos grados tiene la suma de los ángulos internos de un hexágono?', 'options' => ['360°', '540°', '720°', '1080°'], 'correct_answer' => '2', 'explanation' => 'Fórmula: (n-2) × 180° = (6-2) × 180° = 720°.', 'points' => 2, 'order' => 4],
                ],
            ],
            [
                'title' => 'Examen de Trigonometría - Funciones Trigonométricas',
                'description' => 'Evaluación sobre seno, coseno, tangente y sus aplicaciones en triángulos rectángulos.',
                'unit' => 'Trigonometría',
                'difficulty' => 'advanced',
                'time_limit' => 50,
                'max_attempts' => 1,
                'auto_correct' => true,
                'randomize_questions' => false,
                'is_active' => false,
                'is_published' => false,
                'published_at' => null,
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de sen(30°)?', 'options' => ['1/2', '√3/2', '1', '√2/2'], 'correct_answer' => '0', 'explanation' => 'sen(30°) = 1/2 = 0.5.', 'points' => 2, 'order' => 0],
                    ['question_text' => 'En un triángulo rectángulo, si el cateto opuesto es 5 y la hipotenusa es 13, ¿cuál es sen(θ)?', 'options' => ['5/12', '5/13', '12/13', '13/5'], 'correct_answer' => '1', 'explanation' => 'sen(θ) = cateto opuesto / hipotenusa = 5/13.', 'points' => 2, 'order' => 1],
                    ['question_text' => '¿Cuál es el valor de cos(60°)?', 'options' => ['1', '1/2', '√3/2', '0'], 'correct_answer' => '1', 'explanation' => 'cos(60°) = 1/2 = 0.5.', 'points' => 2, 'order' => 2],
                    ['question_text' => '¿Cuál es el valor de tan(45°)?', 'options' => ['0', '1/2', '1', '√3'], 'correct_answer' => '2', 'explanation' => 'tan(45°) = sen(45°)/cos(45°) = (√2/2)/(√2/2) = 1.', 'points' => 2, 'order' => 3],
                    ['question_text' => 'Si sen(θ) = 3/5, ¿cuál es cos(θ) sabiendo que θ es un ángulo agudo?', 'options' => ['3/5', '4/5', '5/3', '5/4'], 'correct_answer' => '1', 'explanation' => 'Por Pitágoras: cos(θ) = √(1 - (3/5)²) = √(16/25) = 4/5.', 'points' => 3, 'order' => 4],
                ],
            ],
        ];

        foreach ($exams as $examData) {
            $questions = $examData['questions'];
            unset($examData['questions']);

            $totalPoints = array_sum(array_column($questions, 'points'));

            $exam = Exam::create(array_merge($examData, [
                'id' => Str::uuid(),
                'teacher_id' => $teacher->id,
                'total_questions' => count($questions),
                'total_points' => $totalPoints,
            ]));

            foreach ($questions as $q) {
                // Convención canónica: correct_answer es el TEXTO de la opción
                // (el índice solo se acepta como entrada del seeder legado).
                $options = $q['options'];
                $correct = $q['correct_answer'];
                if (ctype_digit($correct) && isset($options[(int) $correct])) {
                    $correct = (string) $options[(int) $correct];
                }

                Question::create([
                    'id' => Str::uuid(),
                    'evaluation_id' => $exam->id,
                    'type' => 'multiple_choice',
                    'question_text' => $q['question_text'],
                    'options' => $options,
                    'correct_answer' => $correct,
                    'explanation' => $q['explanation'],
                    'points' => $q['points'],
                    'order' => $q['order'],
                ]);
            }
        }
    }
}
