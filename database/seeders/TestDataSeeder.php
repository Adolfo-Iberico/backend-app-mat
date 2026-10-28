<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\InstitutionConfig;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Notification;
use App\Models\Question;
use App\Models\Role;
use App\Models\StudentAnswer;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Construye un conjunto coherente de usuarios y actividad académica para pruebas.
 */
class TestDataSeeder extends Seeder
{
    private array $teachers = [];

    private array $students = [];

    private array $lessons = [];

    private array $evaluations = [];

    private array $questions = [];

    /**
     * Orquesta la creación de cuentas, contenidos, actividad y configuración de prueba.
     */
    public function run(): void
    {
        $this->command->info('Ingresando datos de prueba...');

        $this->createTeachers();
        $this->createStudents();
        $this->createLessons();
        $this->createEvaluations();
        $this->createQuestions();
        $this->createLessonProgress();
        $this->createEvaluationResults();
        $this->refreshStudentProfiles();
        $this->createAcademicPeriods();
        $this->createNotifications();
        $this->createInstitutionConfig();

        $this->command->info('Datos de prueba ingresados correctamente.');
    }

    /**
     * Crea los docentes de muestra y sus perfiles cuando aún no existen.
     */
    private function createTeachers(): void
    {

        $teachersData = [
            [
                'full_name' => 'Carlos Mendoza Rivera',
                'email' => 'profesor1@mathflow.com',
                'department' => 'Matematicas',
                'specialization' => 'Algebra y Calculo',
                'years_experience' => 12,
            ],
            [
                'full_name' => 'Maria Elena Torres',
                'email' => 'profesor2@mathflow.com',
                'department' => 'Matematicas',
                'specialization' => 'Geometria y Trigonometria',
                'years_experience' => 8,
            ],
            [
                'full_name' => 'Roberto Sanchez Lima',
                'email' => 'profesor3@mathflow.com',
                'department' => 'Ciencias',
                'specialization' => 'Estadistica y Probabilidad',
                'years_experience' => 15,
            ],
        ];

        foreach ($teachersData as $data) {
            $teacher = User::where('email', $data['email'])->first();

            if (! $teacher) {
                $teacher = User::create([
                    'id' => Str::uuid(),
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'password' => 'password123',
                    'role' => Role::TEACHER,
                    'is_active' => true,
                    'provider' => 'email',
                    'email_verified_at' => now(),
                ]);

                TeacherProfile::create([
                    'id' => Str::uuid(),
                    'user_id' => $teacher->id,
                    'department' => $data['department'],
                    'specialization' => $data['specialization'],
                    'years_experience' => $data['years_experience'],
                ]);
            }

            $this->teachers[] = $teacher;
        }

        $this->command->info('  3 profesores creados');
    }

    /**
     * Crea estudiantes de muestra y sus perfiles académicos iniciales.
     */
    private function createStudents(): void
    {

        $studentsData = [
            ['full_name' => 'Ana Garcia Lopez', 'grade' => '3ro Secundaria', 'academic_level' => 'advanced'],
            ['full_name' => 'Luis Hernandez Ruiz', 'grade' => '2do Secundaria', 'academic_level' => 'intermediate'],
            ['full_name' => 'Sofia Martinez Diaz', 'grade' => '3ro Secundaria', 'academic_level' => 'advanced'],
            ['full_name' => 'Diego Ramirez Castro', 'grade' => '1ro Secundaria', 'academic_level' => 'basic'],
            ['full_name' => 'Valentina Torres Reyes', 'grade' => '2do Secundaria', 'academic_level' => 'intermediate'],
            ['full_name' => 'Mateo Jimenez Vargas', 'grade' => '1ro Secundaria', 'academic_level' => 'basic'],
            ['full_name' => 'Isabella Cruz Flores', 'grade' => '3ro Secundaria', 'academic_level' => 'intermediate'],
            ['full_name' => 'Santiago Morales Ortiz', 'grade' => '1ro Secundaria', 'academic_level' => 'basic'],
            ['full_name' => 'Camila Vargas Delgado', 'grade' => '2do Secundaria', 'academic_level' => 'intermediate'],
            ['full_name' => 'Nicolas Rojas Peña', 'grade' => '3ro Secundaria', 'academic_level' => 'advanced'],
        ];

        foreach ($studentsData as $i => $data) {
            $email = 'estudiante'.($i + 1).'@mathflow.com';
            $student = User::where('email', $email)->first();

            if (! $student) {
                $student = User::create([
                    'id' => Str::uuid(),
                    'full_name' => $data['full_name'],
                    'email' => $email,
                    'password' => 'password123',
                    'role' => Role::STUDENT,
                    'is_active' => true,
                    'provider' => 'email',
                    'grade' => $data['grade'],
                    'email_verified_at' => now(),
                ]);

                StudentProfile::create([
                    'id' => Str::uuid(),
                    'user_id' => $student->id,
                    'academic_level' => $data['academic_level'],
                    'current_streak' => 0,
                ]);
            }

            $this->students[] = $student;
        }

        $this->command->info('  10 estudiantes creados');
    }

    /**
     * Registra las lecciones de prueba y adjunta recursos según su unidad.
     */
    private function createLessons(): void
    {
        $teacher = $this->teachers[0];

        $lessonsData = [
            // Unidad 1: Algebra
            [
                'title' => 'Ecuaciones Lineales',
                'grade' => '1ro',
                'description' => 'Aprende a resolver ecuaciones de primer grado con una incognita.',
                'content' => '<h2>Ecuaciones Lineales</h2><p>Una <strong>ecuacion lineal</strong> es una igualdad matematica que contiene una o mas variables elevadas a la potencia unitaria.</p><h3>Forma general</h3><p>La forma general de una ecuacion lineal es:</p><p><code>ax + b = c</code></p><p>Donde:</p><ul><li><strong>a</strong>, <strong>b</strong> y <strong>c</strong> son constantes</li><li><strong>x</strong> es la variable incognita</li></ul><h3>Pasos para resolver</h3><ol><li>Eliminar parentesis aplicando la propiedad distributiva</li><li>Agrupar terminos semejantes</li><li>Despejar la variable</li></ol><h3>Ejemplo</h3><p>Resolver: 3x + 5 = 20</p><p>3x = 20 - 5</p><p>3x = 15</p><p>x = 5</p>',
                'unit' => 'Álgebra',
                'topic' => 'Ecuaciones',
                'difficulty' => 'basic',
                'tags' => ['algebra', 'ecuaciones', 'lineales'],
                'estimated_time' => 30,
            ],
            [
                'title' => 'Desigualdades Lineales',
                'grade' => '1ro',
                'description' => 'Resolucion de desigualdades y representacion en la recta numerica.',
                'content' => '<h2>Desigualdades Lineales</h2><p>Una <strong>desigualdad lineal</strong> relaciona dos expresiones mediante un signo de desigualdad.</p><h3>Signos de desigualdad</h3><ul><li><strong>&lt;</strong> menor que</li><li><strong>&gt;</strong> mayor que</li><li><strong>&le;</strong> menor o igual que</li><li><strong>&ge;</strong> mayor o igual que</li></ul><h3>Regla importante</h3><p>Al multiplicar o dividir ambos lados por un numero negativo, el sentido de la desigualdad se <strong>invierte</strong>.</p><h3>Ejemplo</h3><p>Resolver: 2x - 3 &gt; 7</p><p>2x &gt; 10</p><p>x &gt; 5</p>',
                'unit' => 'Álgebra',
                'topic' => 'Desigualdades',
                'difficulty' => 'basic',
                'tags' => ['algebra', 'desigualdades'],
                'estimated_time' => 25,
            ],
            [
                'title' => 'Funciones Lineales y Cuadraticas',
                'grade' => '2do',
                'description' => 'Estudio de funciones de primer y segundo grado, graficas y propiedades.',
                'content' => '<h2>Funciones</h2><h3>Funcion Lineal</h3><p>Una funcion lineal tiene la forma: <code>f(x) = mx + b</code></p><ul><li><strong>m</strong> = pendiente (inclinacion de la recta)</li><li><strong>b</strong> = ordenada al origen</li></ul><h3>Funcion Cuadratica</h3><p>Una funcion cuadratica tiene la forma: <code>f(x) = ax² + bx + c</code></p><p>Donde <strong>a &ne; 0</strong>. Su grafica es una <strong>parabola</strong>.</p><h3>Vertice de la parabola</h3><p>El vertice se encuentra en: <code>x = -b / (2a)</code></p><h3>Ejemplo</h3><p>f(x) = x² - 4x + 3</p><p>Vertice: x = 4/2 = 2</p><p>f(2) = 4 - 8 + 3 = -1</p><p>Vertice: (2, -1)</p>',
                'unit' => 'Álgebra',
                'topic' => 'Funciones',
                'difficulty' => 'intermediate',
                'tags' => ['algebra', 'funciones', 'lineales', 'cuadraticas'],
                'estimated_time' => 45,
            ],
            [
                'title' => 'Sistemas de Ecuaciones',
                'grade' => '2do',
                'description' => 'Metodos de resolucion: sustitucion, igualacion y reduccion.',
                'content' => '<h2>Sistemas de Ecuaciones</h2><p>Un <strong>sistema de ecuaciones</strong> es un conjunto de dos o mas ecuaciones con las mismas incognitas.</p><h3>Metodo de Sustitucion</h3><ol><li>Despejar una variable de una ecuacion</li><li>Sustituir en la otra ecuacion</li><li>Resolver la ecuacion resultante</li></ol><h3>Metodo de Reduccion</h3><ol><li>Multiplicar ecuaciones para igualar coeficientes</li><li>Sumar o restar para eliminar una variable</li><li>Resolver la ecuacion resultante</li></ol><h3>Ejemplo</h3><p>x + y = 10</p><p>x - y = 4</p><p>Solucionando: 2x = 14, x = 7, y = 3</p>',
                'unit' => 'Álgebra',
                'topic' => 'Sistemas',
                'difficulty' => 'intermediate',
                'tags' => ['algebra', 'sistemas', 'ecuaciones'],
                'estimated_time' => 40,
            ],
            // Unidad 2: Geometria
            [
                'title' => 'Triangulos y sus Propiedades',
                'grade' => '1ro',
                'description' => 'Clasificacion, angulos, perimetro y area de triangulos.',
                'content' => '<h2>Triangulos</h2><p>Un <strong>triangulo</strong> es un poligono de tres lados y tres vertices.</p><h3>Clasificacion por lados</h3><ul><li><strong>Equilatero:</strong> los tres lados son iguales</li><li><strong>Isosceles:</strong> dos lados iguales</li><li><strong>Escaleno:</strong> los tres lados diferentes</li></ul><h3>Clasificacion por angulos</h3><ul><li><strong>Acutangulo:</strong> todos los angulos menores a 90°</li><li><strong>Rectangulo:</strong> tiene un angulo de 90°</li><li><strong>Obtusangulo:</strong> tiene un angulo mayor a 90°</li></ul><h3>Area</h3><p><code>A = (base × altura) / 2</code></p><h3>Teorema de Pitagoras</h3><p>En un triangulo rectangulo: <code>c² = a² + b²</code></p>',
                'unit' => 'Geometría',
                'topic' => 'Triangulos',
                'difficulty' => 'basic',
                'tags' => ['geometria', 'triangulos', 'area'],
                'estimated_time' => 35,
            ],
            [
                'title' => 'Circunferencia y Circulo',
                'grade' => '2do',
                'description' => 'Propiedades del circulo, perimetro y area.',
                'content' => '<h2>Circunferencia y Circulo</h2><h3>Circunferencia</h3><p>Es el conjunto de puntos que estan a una distancia fija (radio) de un punto central.</p><p><strong>Longitud:</strong> <code>L = 2πr</code></p><h3>Circulo</h3><p>Es la region delimitada por la circunferencia.</p><p><strong>Area:</strong> <code>A = πr²</code></p><h3>Relaciones importantes</h3><ul><li><strong>Diametro:</strong> <code>d = 2r</code></li><li><strong>Arco:</strong> porcion de la circunferencia</li><li><strong>Chord:</strong> segmento que une dos puntos de la circunferencia</li></ul><h3>Ejemplo</h3><p>Radio = 5 cm</p><p>L = 2 × 3.1416 × 5 = 31.42 cm</p><p>A = 3.1416 × 25 = 78.54 cm²</p>',
                'unit' => 'Geometría',
                'topic' => 'Circulos',
                'difficulty' => 'basic',
                'tags' => ['geometria', 'circulos', 'pi'],
                'estimated_time' => 30,
            ],
            [
                'title' => 'Areas de Poligonos',
                'grade' => '3ro',
                'description' => 'Calculo de areas de rectangulos, trapecios, rombos y poligonos regulares.',
                'content' => '<h2>Areas de Poligonos</h2><h3>Rectangulo</h3><p><code>A = base × altura</code></p><h3>Cuadrado</h3><p><code>A = lado²</code></p><h3>Trapecio</h3><p><code>A = (base mayor + base menor) × altura / 2</code></p><h3>Rombo</h3><p><code>A = (diagonal mayor × diagonal menor) / 2</code></p><h3>Poligono Regular</h3><p><code>A = (perimetro × apotema) / 2</code></p><h3>Ejemplo - Trapecio</h3><p>B1 = 10, B2 = 6, h = 4</p><p>A = (10 + 6) × 4 / 2 = 32 cm²</p>',
                'unit' => 'Geometría',
                'topic' => 'Areas',
                'difficulty' => 'intermediate',
                'tags' => ['geometria', 'areas', 'poligonos'],
                'estimated_time' => 40,
            ],
            [
                'title' => 'Volumenes de Solidos',
                'grade' => '4to',
                'description' => 'Calculo de volumenes de cubos, cilindros, conos y esferas.',
                'content' => '<h2>Volumenes de Solidos</h2><h3>Cubo</h3><p><code>V = lado³</code></p><h3>Paralelepipedo</h3><p><code>V = largo × ancho × alto</code></p><h3>Cilindro</h3><p><code>V = πr²h</code></h3><h3>Cono</h3><p><code>V = πr²h / 3</code></p><h3>Esfera</h3><p><code>V = 4πr³ / 3</code></p><h3>Ejemplo - Cilindro</h3><p>r = 3 cm, h = 10 cm</p><p>V = 3.1416 × 9 × 10 = 282.74 cm³</p>',
                'unit' => 'Geometría',
                'topic' => 'Volumenes',
                'difficulty' => 'advanced',
                'tags' => ['geometria', 'volumenes', 'solidos'],
                'estimated_time' => 45,
            ],
            // Unidad 3: Numeros
            [
                'title' => 'Fracciones y Operaciones',
                'grade' => '1ro',
                'description' => 'Operaciones con fracciones: suma, resta, multiplicacion y division.',
                'content' => '<h2>Fracciones</h2><p>Una <strong>fraccion</strong> representa una parte de un todo.</p><h3>Partes</h3><ul><li><strong>Numerador:</strong> parte que se toma</li><li><strong>Denominador:</strong> total de partes iguales</li></ul><h3>Suma y Resta</h3><p>Mismo denominador: <code>a/c ± b/c = (a ± b)/c</code></p><p>Distinto denominador: buscar MCM</p><h3>Multiplicacion</h3><p><code>(a/b) × (c/d) = (a×c)/(b×d)</code></p><h3>Division</h3><p><code>(a/b) ÷ (c/d) = (a/b) × (d/c)</code></p><h3>Ejemplo</h3><p>1/2 + 1/3 = 3/6 + 2/6 = 5/6</p>',
                'unit' => 'Números',
                'topic' => 'Fracciones',
                'difficulty' => 'basic',
                'tags' => ['numeros', 'fracciones', 'operaciones'],
                'estimated_time' => 30,
            ],
            [
                'title' => 'Decimales y Porcentajes',
                'grade' => '1ro',
                'description' => 'Conversion entre decimales, fracciones y porcentajes.',
                'content' => '<h2>Decimales y Porcentajes</h2><h3>Decimales</h3><p>Los numeros decimales tienen una parte entera y una parte decimal separadas por una coma o punto.</p><h3>Conversion Decimal a Fraccion</h3><p>Escribir los decimales como numerador y 10, 100, 1000... como denominador.</p><p>Ejemplo: 0.75 = 75/100 = 3/4</p><h3>Porcentajes</h3><p>Un <strong>porcentaje</strong> es una fraccion con denominador 100.</p><p><code>% = decimal × 100</code></p><h3>Calculo de descuentos</h3><p><code>Precio final = Precio × (1 - descuento/100)</code></p><h3>Ejemplo</h3><p>25% de 80 = 0.25 × 80 = 20</p>',
                'unit' => 'Números',
                'topic' => 'Decimales',
                'difficulty' => 'basic',
                'tags' => ['numeros', 'decimales', 'porcentajes'],
                'estimated_time' => 25,
            ],
            [
                'title' => 'Potencias y Raices',
                'grade' => '3ro',
                'description' => 'Propiedades de potencias, raices cuadradas y cubicas.',
                'content' => '<h2>Potencias y Raices</h2><h3>Potencias</h3><p><code>a^n = a × a × ... × a</code> (n veces)</p><h3>Propiedades</h3><ul><li><code>a^m × a^n = a^(m+n)</code></li><li><code>a^m / a^n = a^(m-n)</code></li><li><code>(a^m)^n = a^(m×n)</code></li><li><code>a^0 = 1</code></li><li><code>a^(-n) = 1/a^n</code></li></ul><h3>Raices</h3><p>La raiz enesima de a es el numero que elevado a n da a.</p><p><code>√a = a^(1/2)</code></p><h3>Ejemplo</h3><p>2^3 × 2^4 = 2^7 = 128</p><p>√144 = 12</p>',
                'unit' => 'Números',
                'topic' => 'Potencias',
                'difficulty' => 'intermediate',
                'tags' => ['numeros', 'potencias', 'raices'],
                'estimated_time' => 35,
            ],
            [
                'title' => 'Notacion Cientifica',
                'grade' => '5to',
                'description' => 'Expresar numeros grandes y pequenos en notacion cientifica.',
                'content' => '<h2>Notacion Cientifica</h2><p>La <strong>notacion cientifica</strong> expresa numeros como el producto de un numero entre 1 y 10 por una potencia de 10.</p><h3>Formato</h3><p><code>a × 10^n</code></p><p>Donde 1 &le; |a| &lt; 10 y n es un entero.</p><h3>Para numeros grandes (n &gt; 0)</h3><p>3,500,000 = 3.5 × 10^6</p><h3>Para numeros pequenos (n &lt; 0)</h3><p>0.00042 = 4.2 × 10^(-4)</p><h3>Operaciones</h3><p>Multiplicar: multiplicar coeficientes y sumar exponentes</p><p>Dividir: dividir coeficientes y restar exponentes</p><h3>Ejemplo</h3><p>(2 × 10^3) × (3 × 10^4) = 6 × 10^7</p>',
                'unit' => 'Números',
                'topic' => 'Notacion Cientifica',
                'difficulty' => 'advanced',
                'tags' => ['numeros', 'notacion', 'cientifica'],
                'estimated_time' => 30,
            ],
        ];

        foreach ($lessonsData as $i => $data) {
            $lesson = Lesson::create([
                'id' => Str::uuid(),
                'title' => $data['title'],
                'description' => $data['description'],
                'content' => $data['content'],
                'teacher_id' => $teacher->id,
                'grade' => $data['grade'],
                'unit' => $data['unit'],
                'topic' => $data['topic'],
                'difficulty' => $data['difficulty'],
                'tags' => $data['tags'],
                'estimated_time' => $data['estimated_time'],
                'is_published' => true,
                'resources' => $this->generateResources($data['unit']),
                'order' => $i + 1,
            ]);

            $this->lessons[] = $lesson;
        }

        $this->command->info('  12 lecciones creadas y publicadas');
    }

    /**
     * Devuelve los recursos de ejemplo asociados a una unidad curricular.
     */
    private function generateResources(string $unit): array
    {
        $resources = [
            'Álgebra' => [
                ['type' => 'pdf', 'url' => '/resources/algebra-guia.pdf', 'title' => 'Guia de Algebra'],
                ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=example1', 'title' => 'Video explicativo'],
            ],
            'Geometría' => [
                ['type' => 'pdf', 'url' => '/resources/geometria-formulas.pdf', 'title' => 'Formulas Geometricas'],
                ['type' => 'image', 'url' => '/resources/geometria-diagrama.png', 'title' => 'Diagramas geometricos'],
            ],
            'Números' => [
                ['type' => 'pdf', 'url' => '/resources/numeros-ejercicios.pdf', 'title' => 'Ejercicios de Numeros'],
                ['type' => 'link', 'url' => 'https://www.khanacademy.org/math', 'title' => 'Khan Academy'],
            ],
        ];

        $unitResources = $resources[$unit] ?? [];

        return collect($unitResources)
            ->map(fn (array $resource) => array_merge(['id' => Str::uuid()], $resource))
            ->all();
    }

    /**
     * Crea las evaluaciones de muestra vinculadas a las lecciones correspondientes.
     */
    private function createEvaluations(): void
    {
        $teacher = $this->teachers[0];

        $evaluationsData = [
            // Algebra
            [
                'title' => 'Examen de Ecuaciones Lineales',
                'description' => 'Evalua el conocimiento sobre resolucion de ecuaciones de primer grado.',
                'lesson_index' => 0,
                'type' => 'exam',
                'difficulty' => 'basic',
                'time_limit' => 45,
                'max_attempts' => 1,
            ],
            [
                'title' => 'Quiz de Desigualdades',
                'description' => 'Evaluacion rapida sobre desigualdades lineales.',
                'lesson_index' => 1,
                'type' => 'quiz',
                'difficulty' => 'basic',
                'time_limit' => 15,
                'max_attempts' => 2,
            ],
            [
                'title' => 'Tarea de Funciones',
                'description' => 'Ejercicios sobre funciones lineales y cuadraticas.',
                'lesson_index' => 2,
                'type' => 'homework',
                'difficulty' => 'intermediate',
                'time_limit' => 60,
                'max_attempts' => 3,
            ],
            [
                'title' => 'Practica de Sistemas de Ecuaciones',
                'description' => 'Resolucion de sistemas por diferentes metodos.',
                'lesson_index' => 3,
                'type' => 'practice',
                'difficulty' => 'intermediate',
                'time_limit' => 40,
                'max_attempts' => 2,
            ],
            // Geometria
            [
                'title' => 'Examen de Geometria Basica',
                'description' => 'Triangulos, circulos y area de poligonos.',
                'lesson_index' => 4,
                'type' => 'exam',
                'difficulty' => 'basic',
                'time_limit' => 50,
                'max_attempts' => 1,
            ],
            [
                'title' => 'Quiz de Volumenes',
                'description' => 'Calculo de volumenes de solidos geometricos.',
                'lesson_index' => 7,
                'type' => 'quiz',
                'difficulty' => 'advanced',
                'time_limit' => 20,
                'max_attempts' => 2,
            ],
            // Numeros
            [
                'title' => 'Examen de Fracciones y Decimales',
                'description' => 'Operaciones con fracciones, decimales y porcentajes.',
                'lesson_index' => 8,
                'type' => 'exam',
                'difficulty' => 'basic',
                'time_limit' => 40,
                'max_attempts' => 1,
            ],
            [
                'title' => 'Tarea de Potencias y Raices',
                'description' => 'Ejercicios de potenciacion y radicacion.',
                'lesson_index' => 10,
                'type' => 'homework',
                'difficulty' => 'intermediate',
                'time_limit' => 50,
                'max_attempts' => 2,
            ],
        ];

        $dueDate = now()->addDays(15);

        foreach ($evaluationsData as $data) {
            $evaluation = Evaluation::create([
                'id' => Str::uuid(),
                'title' => $data['title'],
                'description' => $data['description'],
                'teacher_id' => $teacher->id,
                'lesson_id' => $this->lessons[$data['lesson_index']]->id,
                'type' => $data['type'],
                'difficulty' => $data['difficulty'],
                'time_limit' => $data['time_limit'],
                'due_date' => $dueDate,
                'is_published' => true,
                'auto_correct' => true,
                'randomize_questions' => false,
                'max_attempts' => $data['max_attempts'],
            ]);

            $this->evaluations[] = $evaluation;
        }

        $this->command->info('  8 evaluaciones creadas y publicadas');
    }

    /**
     * Registra las preguntas predefinidas y las asocia a cada evaluación de prueba.
     */
    private function createQuestions(): void
    {
        $questionsPerEvaluation = [
            // Evaluacion 0: Ecuaciones Lineales
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Resuelve: 2x + 6 = 14',
                    'options' => [['label' => 'A', 'value' => 'x = 2'], ['label' => 'B', 'value' => 'x = 4'], ['label' => 'C', 'value' => 'x = 6'], ['label' => 'D', 'value' => 'x = 8']],
                    'correct_answer' => 'x = 4',
                    'explanation' => '2x = 14 - 6 = 8, entonces x = 4',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cual es el valor de x en: 5x - 3 = 2x + 9?',
                    'options' => [['label' => 'A', 'value' => 'x = 2'], ['label' => 'B', 'value' => 'x = 3'], ['label' => 'C', 'value' => 'x = 4'], ['label' => 'D', 'value' => 'x = 5']],
                    'correct_answer' => 'x = 4',
                    'explanation' => '3x = 12, x = 4',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Si 3(x - 2) = 15, entonces x = ___',
                    'options' => null,
                    'correct_answer' => '7',
                    'explanation' => '3x - 6 = 15, 3x = 21, x = 7',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Que tipo de ecuacion es: 4x + 7 = 4x + 3?',
                    'options' => [['label' => 'A', 'value' => 'Tiene solucion unica'], ['label' => 'B', 'value' => 'No tiene solucion'], ['label' => 'C', 'value' => 'Tiene infinitas soluciones'], ['label' => 'D', 'value' => 'Es cuadratica']],
                    'correct_answer' => 'No tiene solucion',
                    'explanation' => 'Al restar 4x de ambos lados queda 7 = 3, lo cual es falso',
                    'points' => 3,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Resuelve: (x/2) + 5 = 10',
                    'options' => [['label' => 'A', 'value' => 'x = 5'], ['label' => 'B', 'value' => 'x = 8'], ['label' => 'C', 'value' => 'x = 10'], ['label' => 'D', 'value' => 'x = 15']],
                    'correct_answer' => 'x = 10',
                    'explanation' => 'x/2 = 5, x = 10',
                    'points' => 1,
                ],
            ],
            // Evaluacion 1: Desigualdades
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Resuelve: x + 3 > 7',
                    'options' => [['label' => 'A', 'value' => 'x > 4'], ['label' => 'B', 'value' => 'x > 10'], ['label' => 'C', 'value' => 'x < 4'], ['label' => 'D', 'value' => 'x > 3']],
                    'correct_answer' => 'x > 4',
                    'explanation' => 'x > 7 - 3 = 4',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Resuelve: -2x ≤ 8',
                    'options' => [['label' => 'A', 'value' => 'x ≤ -4'], ['label' => 'B', 'value' => 'x ≥ -4'], ['label' => 'C', 'value' => 'x ≤ 4'], ['label' => 'D', 'value' => 'x ≥ 4']],
                    'correct_answer' => 'x ≥ -4',
                    'explanation' => 'Al dividir por -2 se invierte la desigualdad',
                    'points' => 3,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Si 4x - 1 > 15, entonces x > ___',
                    'options' => null,
                    'correct_answer' => '4',
                    'explanation' => '4x > 16, x > 4',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cual es la representacion de x > 3 en la recta numerica?',
                    'options' => [['label' => 'A', 'value' => 'Circulo abierto en 3, sombra a la derecha'], ['label' => 'B', 'value' => 'Circulo cerrado en 3, sombra a la derecha'], ['label' => 'C', 'value' => 'Circulo abierto en 3, sombra a la izquierda'], ['label' => 'D', 'value' => 'Circulo cerrado en 3, sombra a la izquierda']],
                    'correct_answer' => 'Circulo abierto en 3, sombra a la derecha',
                    'explanation' => 'Mayor que implica circulo abierto y sombra a la derecha',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Completa: 3(2x - 1) ≤ 21 implica x ≤ ___',
                    'options' => null,
                    'correct_answer' => '4',
                    'explanation' => '6x - 3 ≤ 21, 6x ≤ 24, x ≤ 4',
                    'points' => 1,
                ],
            ],
            // Evaluacion 2: Funciones
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cual es la pendiente de la recta f(x) = 3x - 5?',
                    'options' => [['label' => 'A', 'value' => '3'], ['label' => 'B', 'value' => '-5'], ['label' => 'C', 'value' => '5'], ['label' => 'D', 'value' => '-3']],
                    'correct_answer' => '3',
                    'explanation' => 'La pendiente es el coeficiente de x, que es 3',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cual es el vertice de f(x) = x² - 6x + 8?',
                    'options' => [['label' => 'A', 'value' => '(3, -1)'], ['label' => 'B', 'value' => '(-3, -1)'], ['label' => 'C', 'value' => '(3, 1)'], ['label' => 'D', 'value' => '(6, 8)']],
                    'correct_answer' => '(3, -1)',
                    'explanation' => 'x = -(-6)/(2×1) = 3, f(3) = 9 - 18 + 8 = -1',
                    'points' => 3,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Si f(x) = 2x + 1, entonces f(5) = ___',
                    'options' => null,
                    'correct_answer' => '11',
                    'explanation' => 'f(5) = 2(5) + 1 = 11',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Una funcion cuadratica con a > 0 tiene una grafica que es:',
                    'options' => [['label' => 'A', 'value' => 'Parabola hacia arriba'], ['label' => 'B', 'value' => 'Parabola hacia abajo'], ['label' => 'C', 'value' => 'Recta'], ['label' => 'D', 'value' => 'Circulo']],
                    'correct_answer' => 'Parabola hacia arriba',
                    'explanation' => 'Cuando a > 0 la parabola se abre hacia arriba',
                    'points' => 1,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cuales son las raices de f(x) = x² - 5x + 6?',
                    'options' => [['label' => 'A', 'value' => 'x = 2 y x = 3'], ['label' => 'B', 'value' => 'x = 1 y x = 6'], ['label' => 'C', 'value' => 'x = -2 y x = -3'], ['label' => 'D', 'value' => 'x = 5 y x = 6']],
                    'correct_answer' => 'x = 2 y x = 3',
                    'explanation' => '(x-2)(x-3) = 0, entonces x = 2 o x = 3',
                    'points' => 2,
                ],
            ],
            // Evaluacion 3: Sistemas
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Resuelve el sistema: x + y = 10, x - y = 2. Cual es el valor de x?',
                    'options' => [['label' => 'A', 'value' => 'x = 4'], ['label' => 'B', 'value' => 'x = 5'], ['label' => 'C', 'value' => 'x = 6'], ['label' => 'D', 'value' => 'x = 8']],
                    'correct_answer' => 'x = 6',
                    'explanation' => 'Sumando: 2x = 12, x = 6',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'En el sistema anterior, y = ___',
                    'options' => null,
                    'correct_answer' => '4',
                    'explanation' => '6 + y = 10, y = 4',
                    'points' => 1,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cuantas soluciones tiene el sistema: 2x + y = 6, 4x + 2y = 12?',
                    'options' => [['label' => 'A', 'value' => 'Ninguna'], ['label' => 'B', 'value' => 'Una'], ['label' => 'C', 'value' => 'Infinitas'], ['label' => 'D', 'value' => 'Dos']],
                    'correct_answer' => 'Infinitas',
                    'explanation' => 'La segunda ecuacion es el doble de la primera, son la misma recta',
                    'points' => 3,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Usando sustitucion en y = 2x y x + y = 9, el valor de x es:',
                    'options' => [['label' => 'A', 'value' => 'x = 2'], ['label' => 'B', 'value' => 'x = 3'], ['label' => 'C', 'value' => 'x = 4'], ['label' => 'D', 'value' => 'x = 5']],
                    'correct_answer' => 'x = 3',
                    'explanation' => 'x + 2x = 9, 3x = 9, x = 3',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Si 3x + 2y = 16 y x = 2, entonces y = ___',
                    'options' => null,
                    'correct_answer' => '5',
                    'explanation' => '6 + 2y = 16, 2y = 10, y = 5',
                    'points' => 2,
                ],
            ],
            // Evaluacion 4: Geometria Basica
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cual es el area de un triangulo con base 10 cm y altura 6 cm?',
                    'options' => [['label' => 'A', 'value' => '30 cm²'], ['label' => 'B', 'value' => '60 cm²'], ['label' => 'C', 'value' => '16 cm²'], ['label' => 'D', 'value' => '24 cm²']],
                    'correct_answer' => '30 cm²',
                    'explanation' => 'A = (10 × 6) / 2 = 30 cm²',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'En un triangulo rectangulo con catetos 3 y 4, la hipotenusa mide:',
                    'options' => [['label' => 'A', 'value' => '5'], ['label' => 'B', 'value' => '7'], ['label' => 'C', 'value' => '6'], ['label' => 'D', 'value' => '12']],
                    'correct_answer' => '5',
                    'explanation' => 'c² = 3² + 4² = 9 + 16 = 25, c = 5',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'La longitud de una circunferencia con radio 7 cm es ___ cm (usa pi = 3.14)',
                    'options' => null,
                    'correct_answer' => '43.96',
                    'explanation' => 'L = 2 × 3.14 × 7 = 43.96 cm',
                    'points' => 3,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'El area de un circulo con radio 5 cm es:',
                    'options' => [['label' => 'A', 'value' => '25π cm²'], ['label' => 'B', 'value' => '10π cm²'], ['label' => 'C', 'value' => '50π cm²'], ['label' => 'D', 'value' => '5π cm²']],
                    'correct_answer' => '25π cm²',
                    'explanation' => 'A = πr² = π(5²) = 25π cm²',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Un triangulo con lados 5, 5 y 8 es un triangulo:',
                    'options' => [['label' => 'A', 'value' => 'Equilatero'], ['label' => 'B', 'value' => 'Isosceles'], ['label' => 'C', 'value' => 'Escaleno'], ['label' => 'D', 'value' => 'Rectangulo']],
                    'correct_answer' => 'Isosceles',
                    'explanation' => 'Tiene dos lados iguales (5 y 5)',
                    'points' => 1,
                ],
            ],
            // Evaluacion 5: Volumenes
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'El volumen de un cubo con lado 4 cm es:',
                    'options' => [['label' => 'A', 'value' => '16 cm³'], ['label' => 'B', 'value' => '64 cm³'], ['label' => 'C', 'value' => '48 cm³'], ['label' => 'D', 'value' => '12 cm³']],
                    'correct_answer' => '64 cm³',
                    'explanation' => 'V = 4³ = 64 cm³',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'El volumen de un cilindro con radio 3 cm y altura 10 cm es ___ cm³ (redondea a 2 decimales)',
                    'options' => null,
                    'correct_answer' => '282.74',
                    'explanation' => 'V = π(3²)(10) = 90π ≈ 282.74 cm³',
                    'points' => 3,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'El volumen de un cono es:',
                    'options' => [['label' => 'A', 'value' => 'πr²h'], ['label' => 'B', 'value' => 'πr²h/3'], ['label' => 'C', 'value' => '4πr³/3'], ['label' => 'D', 'value' => '2πrh']],
                    'correct_answer' => 'πr²h/3',
                    'explanation' => 'El volumen del cono es un tercio del cilindro',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Si el volumen de una esfera es (4/3)π(6³) cm³, su radio es:',
                    'options' => [['label' => 'A', 'value' => '3 cm'], ['label' => 'B', 'value' => '6 cm'], ['label' => 'C', 'value' => '12 cm'], ['label' => 'D', 'value' => '18 cm']],
                    'correct_answer' => '6 cm',
                    'explanation' => 'El radio es directamente el valor dentro del cubo',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Un cubo tiene volumen de 125 cm³. Su lado mide ___ cm',
                    'options' => null,
                    'correct_answer' => '5',
                    'explanation' => 'l³ = 125, l = ∛125 = 5',
                    'points' => 1,
                ],
            ],
            // Evaluacion 6: Fracciones y Decimales
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Cual es el resultado de 3/4 + 1/6?',
                    'options' => [['label' => 'A', 'value' => '4/10'], ['label' => 'B', 'value' => '11/12'], ['label' => 'C', 'value' => '9/12'], ['label' => 'D', 'value' => '1/2']],
                    'correct_answer' => '11/12',
                    'explanation' => 'MCM = 12: 9/12 + 2/12 = 11/12',
                    'points' => 2,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => '2/3 de 45 es ___',
                    'options' => null,
                    'correct_answer' => '30',
                    'explanation' => '(2/3) × 45 = 90/3 = 30',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => '35% como decimal es:',
                    'options' => [['label' => 'A', 'value' => '0.35'], ['label' => 'B', 'value' => '3.5'], ['label' => 'C', 'value' => '0.035'], ['label' => 'D', 'value' => '35']],
                    'correct_answer' => '0.35',
                    'explanation' => '35/100 = 0.35',
                    'points' => 1,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => 'Si un producto cuesta $200 y tiene 15% de descuento, el precio final es:',
                    'options' => [['label' => 'A', 'value' => '$170'], ['label' => 'B', 'value' => '$180'], ['label' => 'C', 'value' => '$185'], ['label' => 'D', 'value' => '$190']],
                    'correct_answer' => '$170',
                    'explanation' => 'Descuento = 200 × 0.15 = 30, Precio = 200 - 30 = $170',
                    'points' => 3,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => '7/8 como decimal es ___',
                    'options' => null,
                    'correct_answer' => '0.875',
                    'explanation' => '7 ÷ 8 = 0.875',
                    'points' => 2,
                ],
            ],
            // Evaluacion 7: Potencias y Raices
            [
                [
                    'type' => 'multiple_choice',
                    'question_text' => '2^5 es igual a:',
                    'options' => [['label' => 'A', 'value' => '16'], ['label' => 'B', 'value' => '32'], ['label' => 'C', 'value' => '64'], ['label' => 'D', 'value' => '128']],
                    'correct_answer' => '32',
                    'explanation' => '2^5 = 2×2×2×2×2 = 32',
                    'points' => 1,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => '√169 = ___',
                    'options' => null,
                    'correct_answer' => '13',
                    'explanation' => '13² = 169, entonces √169 = 13',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => '3^4 × 3^2 = 3^?',
                    'options' => [['label' => 'A', 'value' => '3^6'], ['label' => 'B', 'value' => '3^8'], ['label' => 'C', 'value' => '9^6'], ['label' => 'D', 'value' => '3^2']],
                    'correct_answer' => '3^6',
                    'explanation' => 'Al multiplicar potencias de igual base se suman los exponentes: 4+2=6',
                    'points' => 2,
                ],
                [
                    'type' => 'multiple_choice',
                    'question_text' => '5^0 es igual a:',
                    'options' => [['label' => 'A', 'value' => '0'], ['label' => 'B', 'value' => '1'], ['label' => 'C', 'value' => '5'], ['label' => 'D', 'value' => 'Indefinido']],
                    'correct_answer' => '1',
                    'explanation' => 'Cualquier numero elevado a 0 es igual a 1',
                    'points' => 1,
                ],
                [
                    'type' => 'fill_blank',
                    'question_text' => 'Si 2^x = 64, entonces x = ___',
                    'options' => null,
                    'correct_answer' => '6',
                    'explanation' => '2^6 = 64, entonces x = 6',
                    'points' => 3,
                ],
            ],
        ];

        foreach ($this->evaluations as $evalIndex => $evaluation) {
            $questionsData = $questionsPerEvaluation[$evalIndex] ?? [];

            foreach ($questionsData as $order => $qData) {
                $question = Question::create([
                    'id' => Str::uuid(),
                    'evaluation_id' => $evaluation->id,
                    'type' => $qData['type'],
                    'question_text' => $qData['question_text'],
                    'options' => $qData['options'],
                    'correct_answer' => $qData['correct_answer'],
                    'explanation' => $qData['explanation'],
                    'points' => $qData['points'],
                    'order' => $order + 1,
                ]);

                $this->questions[] = $question;
            }
        }

        $this->command->info('  40 preguntas creadas (5 por evaluacion)');
    }

    /**
     * Genera registros aleatorios de avance de lecciones para los estudiantes de muestra.
     */
    private function createLessonProgress(): void
    {
        $count = 0;

        foreach ($this->students as $student) {
            $numLessons = rand(4, 8);
            $selectedLessons = collect($this->lessons)->shuffle()->take($numLessons);

            foreach ($selectedLessons as $lesson) {
                $progressPercent = collect([25, 50, 75, 100])->random();
                $status = $progressPercent == 100
                    ? LessonProgress::STATUS_COMPLETED
                    : LessonProgress::STATUS_IN_PROGRESS;

                LessonProgress::create([
                    'id' => Str::uuid(),
                    'user_id' => $student->id,
                    'lesson_id' => $lesson->id,
                    'progress' => $progressPercent,
                    'status' => $status,
                    'completed_at' => $status === LessonProgress::STATUS_COMPLETED ? now()->subDays(rand(0, 14)) : null,
                    'time_spent' => rand(120, 3600),
                    'last_position' => rand(0, 100),
                ]);

                $count++;
            }
        }

        $this->command->info("  {$count} registros de progreso de lecciones creados");
    }

    /**
     * Genera resultados y respuestas para intentos de evaluaciones de los estudiantes.
     */
    private function createEvaluationResults(): void
    {
        $count = 0;
        $scores = [4.0, 4.5, 5.0, 5.5, 6.0, 6.5, 7.0, 7.5, 8.0, 8.5, 9.0, 9.5, 10.0];

        foreach ($this->students as $student) {
            $numEvals = rand(3, 6);
            $selectedEvals = collect($this->evaluations)->shuffle()->take($numEvals);

            foreach ($selectedEvals as $evaluation) {
                $questions = collect($this->questions)->where('evaluation_id', $evaluation->id);
                $totalQuestions = $questions->count();
                $totalPoints = $questions->sum('points');

                $correctCount = rand(1, $totalQuestions);
                $score = round(($correctCount / $totalQuestions) * 20, 1);
                $score = min($score, 20.0);

                $startedAt = now()->subDays(rand(1, 20))->subHours(rand(0, 12));
                $timeTaken = rand(180, 2400);

                $result = EvaluationResult::create([
                    'id' => Str::uuid(),
                    'user_id' => $student->id,
                    'evaluation_id' => $evaluation->id,
                    'score' => $score,
                    'max_score' => 20.0,
                    'correct_answers' => $correctCount,
                    'total_questions' => $totalQuestions,
                    'time_taken' => $timeTaken,
                    'status' => EvaluationResult::STATUS_COMPLETED,
                    'started_at' => $startedAt,
                    'completed_at' => $startedAt->copy()->addSeconds($timeTaken),
                    'attempt_number' => 1,
                ]);

                $questionsShuffled = collect($questions)->shuffle();
                $correctIndices = range(0, $correctCount - 1);

                foreach ($questionsShuffled as $idx => $question) {
                    $isCorrect = in_array($idx, $correctIndices);

                    StudentAnswer::create([
                        'id' => Str::uuid(),
                        'user_id' => $student->id,
                        'evaluation_result_id' => $result->id,
                        'question_id' => $question->id,
                        'answer' => $isCorrect ? $question->correct_answer : $this->getWrongAnswer($question),
                        'is_correct' => $isCorrect,
                        'points_earned' => $isCorrect ? $question->points : 0,
                    ]);
                }

                $count++;
            }
        }

        $this->command->info("  {$count} resultados de evaluaciones creados con respuestas");
    }

    /**
     * Elige una opción incorrecta disponible o usa una respuesta genérica.
     */
    private function getWrongAnswer(Question $question): string
    {
        if ($question->type === 'multiple_choice' && is_array($question->options)) {
            $wrongOptions = collect($question->options)
                ->where('value', '!=', $question->correct_answer)
                ->pluck('value')
                ->values();

            if ($wrongOptions->isNotEmpty()) {
                return $wrongOptions->random();
            }
        }

        return 'respuesta incorrecta';
    }

    /**
     * Recalcula en los perfiles los totales, promedios y rachas de actividad.
     */
    private function refreshStudentProfiles(): void
    {
        $count = 0;
        foreach ($this->students as $student) {
            $activityDates = LessonProgress::where('user_id', $student->id)
                ->whereNotNull('completed_at')
                ->pluck('completed_at')
                ->merge(
                    EvaluationResult::where('user_id', $student->id)
                        ->where('status', EvaluationResult::STATUS_COMPLETED)
                        ->whereNotNull('completed_at')
                        ->pluck('completed_at')
                );

            $streak = 0;
            if ($activityDates->isNotEmpty()) {
                $days = $activityDates
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->unique()
                    ->sortDesc()
                    ->values();
                $streak = 1;
                $previous = Carbon::parse($days[0]);
                for ($i = 1; $i < $days->count(); $i++) {
                    $current = Carbon::parse($days[$i]);
                    if ($previous->copy()->subDay()->eq($current)) {
                        $streak++;
                        $previous = $current;
                    } else {
                        break;
                    }
                }
            }

            StudentProfile::where('user_id', $student->id)->update([
                'current_streak' => $streak,
            ]);
            $count++;
        }

        $this->command->info("  {$count} perfiles de estudiantes actualizados con datos reales");
    }

    /**
     * Crea los períodos académicos de ejemplo activo e histórico.
     */
    private function createAcademicPeriods(): void
    {
        AcademicPeriod::firstOrCreate([
            'name' => 'Periodo 2026-I',
        ], [
            'id' => Str::uuid(),
            'start_date' => '2026-01-15 00:00:00',
            'end_date' => '2026-06-30 23:59:59',
            'is_active' => true,
            'description' => 'Primer semestre del ano academico 2026',
        ]);

        AcademicPeriod::firstOrCreate([
            'name' => 'Periodo 2025-II',
        ], [
            'id' => Str::uuid(),
            'start_date' => '2025-08-01 00:00:00',
            'end_date' => '2025-12-20 23:59:59',
            'is_active' => false,
            'description' => 'Segundo semestre del ano academico 2025',
        ]);

        $this->command->info('  2 periodos academicos creados');
    }

    /**
     * Distribuye notificaciones de muestra entre los usuarios creados por este seeder.
     */
    private function createNotifications(): void
    {
        $allUsers = array_merge($this->teachers, $this->students);
        $count = 0;

        $notificationsData = [
            ['title' => 'Bienvenido a Aulamate', 'message' => 'Tu cuenta ha sido creada exitosamente. Explora nuestras lecciones de matematicas.', 'type' => Notification::TYPE_SUCCESS],
            ['title' => 'Nueva leccion disponible', 'message' => 'Se ha publicado una nueva leccion de Algebra: Ecuaciones Lineales.', 'type' => Notification::TYPE_INFO],
            ['title' => 'Recordatorio de evaluacion', 'message' => 'Tienes una evaluacion pendiente que vence en 3 dias. No olvides completarla.', 'type' => Notification::TYPE_WARNING],
            ['title' => 'Calificacion publicada', 'message' => 'Tu profesor ha calificado tu ultimo examen. Revisa tu resultado.', 'type' => Notification::TYPE_INFO],
            ['title' => 'Logro desbloqueado', 'message' => 'Felicidades! Has completado tu primera leccion. Sigue asi!', 'type' => Notification::TYPE_SUCCESS],
            ['title' => 'Mantenimiento programado', 'message' => 'El sistema estara en mantenimiento el sabado de 2:00 a 6:00 AM.', 'type' => Notification::TYPE_WARNING],
            ['title' => 'Nueva tarea asignada', 'message' => 'Tu profesor ha asignado una nueva tarea de Geometria. Revisa los detalles.', 'type' => Notification::TYPE_INFO],
            ['title' => 'Racha alcanzada', 'message' => 'Has mantenido una racha de 7 dias consecutivos de estudio. Excelente!', 'type' => Notification::TYPE_SUCCESS],
            ['title' => 'Actualizacion del sistema', 'message' => 'Se han agregado nuevas funcionalidades a la plataforma. Explora las novedades.', 'type' => Notification::TYPE_INFO],
            ['title' => 'Error en envio', 'message' => 'Hubo un problema al enviar tu respuesta. Por favor intentalo de nuevo.', 'type' => Notification::TYPE_ERROR],
        ];

        foreach ($allUsers as $user) {
            $numNotifications = rand(2, 5);
            $selectedNotifications = collect($notificationsData)->shuffle()->take($numNotifications);

            foreach ($selectedNotifications as $notif) {
                Notification::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'title' => $notif['title'],
                    'message' => $notif['message'],
                    'type' => $notif['type'],
                    'is_read' => rand(0, 1) == 1,
                    'link' => null,
                ]);

                $count++;
            }
        }

        $this->command->info("  {$count} notificaciones creadas");
    }

    /**
     * Registra la configuración institucional inicial usada por los datos de prueba.
     */
    private function createInstitutionConfig(): void
    {
        InstitutionConfig::firstOrCreate([], [
            'id' => Str::uuid(),
            'institution_name' => 'Instituto Aulamate',
            'primary_color' => '#004AC6',
            'secondary_color' => '#006C49',
            'logo' => null,
            'email_notifications' => [
                'new_lesson' => true,
                'evaluation_due' => true,
                'grade_published' => true,
                'weekly_report' => false,
            ],
            'backup_frequency' => 'daily',
            'last_backup' => now()->subHours(6),
        ]);

        $this->command->info('  Configuracion de institucion creada');
    }
}
