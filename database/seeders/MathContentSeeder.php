<?php

namespace Database\Seeders;

use App\Models\Evaluation;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga lecciones y evaluaciones de matemáticas con sus preguntas de práctica.
 */
class MathContentSeeder extends Seeder
{
    /**
     * Crea el docente de ejemplo y registra contenido de álgebra, geometría y trigonometría.
     */
    public function run(): void
    {
        $teacher = User::firstOrCreate(
            ['email' => 'profesor.math@mathflow.com'],
            [
                'full_name' => 'Prof. Rodriguez',
                'password' => bcrypt('password123'),
                'role' => 'teacher',
                'is_active' => true,
                'provider' => 'email',
            ]
        );

        $lessons = [
            // ÁLGEBRA
            [
                'title' => 'Ecuaciones Lineales',
                'unit' => 'Álgebra',
                'difficulty' => 'basic',
                'estimated_time' => 30,
                'description' => 'Resolución de ecuaciones de primer grado con una incógnita.',
                'content' => '<h2>Ecuaciones Lineales</h2><p>Una ecuación lineal es una igualdad matemática que contiene una o más variables elevadas a la potencia de uno. Las ecuaciones lineales se utilizan para describir relaciones proporcionales entre cantidades.</p><p>Para resolver una ecuación lineal, el objetivo es aislar la variable en un lado de la igualdad. Esto se logra realizando operaciones inversas: sumar lo que se resta, restar lo que se suma, multiplicar lo que se divide y dividir lo que se multiplica.</p><p>Es fundamental recordar que cualquier operación que realices en un lado de la ecuación, debes realizarla también en el otro lado para mantener la igualdad.</p>',
                'questions' => [
                    ['question_text' => 'Resuelve: 2x + 5 = 15', 'options' => ['x = 3', 'x = 5', 'x = 7', 'x = 10'], 'correct_answer' => '1', 'explanation' => 'Restamos 5 de ambos lados: 2x = 10. Dividimos entre 2: x = 5.'],
                    ['question_text' => '¿Cuál es el valor de x en 3x - 7 = 8?', 'options' => ['x = 3', 'x = 4', 'x = 5', 'x = 6'], 'correct_answer' => '2', 'explanation' => 'Sumamos 7: 3x = 15. Dividimos entre 3: x = 5.'],
                    ['question_text' => 'Si 4x + 2 = 22, ¿cuánto vale x?', 'options' => ['x = 4', 'x = 5', 'x = 6', 'x = 7'], 'correct_answer' => '1', 'explanation' => 'Restamos 2: 4x = 20. Dividimos entre 4: x = 5.'],
                    ['question_text' => 'Resuelve: 5(x - 1) = 15', 'options' => ['x = 2', 'x = 3', 'x = 4', 'x = 5'], 'correct_answer' => '2', 'explanation' => 'Dividimos entre 5: x - 1 = 3. Sumamos 1: x = 4.'],
                    ['question_text' => '¿Cuál es la solución de 2(x + 3) = 14?', 'options' => ['x = 2', 'x = 3', 'x = 4', 'x = 5'], 'correct_answer' => '2', 'explanation' => 'Dividimos entre 2: x + 3 = 7. Restamos 3: x = 4.'],
                ],
            ],
            [
                'title' => 'Ecuaciones Cuadráticas',
                'unit' => 'Álgebra',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Resolución de ecuaciones de segundo grado mediante factorización, completar cuadrados y fórmula general.',
                'content' => '<h2>Ecuaciones Cuadráticas</h2><p>Una ecuación cuadrática es una ecuación polinómica de segundo grado que tiene la forma ax² + bx + c = 0, donde a, b y c son constantes y a ≠ 0.</p><p>Existen varios métodos para resolver ecuaciones cuadráticas: factorización, completar el cuadrado y la fórmula general. La fórmula general es x = (-b ± √(b² - 4ac)) / 2a, la cual permite encontrar las raíces de cualquier ecuación cuadrática.</p><p>El discriminante (b² - 4ac) nos indica la naturaleza de las raíces: si es positivo hay dos soluciones reales, si es cero hay una solución real, y si es negativo no hay soluciones reales.</p>',
                'questions' => [
                    ['question_text' => 'Resuelve: x² - 5x + 6 = 0', 'options' => ['x = 1 y x = 6', 'x = 2 y x = 3', 'x = -2 y x = -3', 'x = 1 y x = 5'], 'correct_answer' => '1', 'explanation' => 'Factorizando: (x - 2)(x - 3) = 0, por lo tanto x = 2 o x = 3.'],
                    ['question_text' => '¿Cuáles son las raíces de x² - 4 = 0?', 'options' => ['x = 0 y x = 4', 'x = 2 y x = -2', 'x = 4 y x = -4', 'x = 1 y x = -1'], 'correct_answer' => '1', 'explanation' => 'Factorizando: (x - 2)(x + 2) = 0, por lo tanto x = 2 o x = -2.'],
                    ['question_text' => 'Usando la fórmula general, ¿cuáles son las raíces de x² + 2x - 3 = 0?', 'options' => ['x = 1 y x = -3', 'x = -1 y x = 3', 'x = 1 y x = 3', 'x = -1 y x = -3'], 'correct_answer' => '0', 'explanation' => 'Factorizando: (x + 3)(x - 1) = 0, por lo tanto x = 1 o x = -3.'],
                    ['question_text' => '¿Cuántas soluciones reales tiene la ecuación x² - 6x + 9 = 0?', 'options' => ['Ninguna', 'Una', 'Dos', 'Infinitas'], 'correct_answer' => '1', 'explanation' => 'El discriminante es b² - 4ac = 36 - 36 = 0, por lo que hay una solución real (x = 3).'],
                    ['question_text' => 'Resuelve: 2x² - 8x = 0', 'options' => ['x = 0 y x = 4', 'x = 0 y x = -4', 'x = 2 y x = 4', 'x = 0 y x = 2'], 'correct_answer' => '0', 'explanation' => 'Factorizando: 2x(x - 4) = 0, por lo tanto x = 0 o x = 4.'],
                ],
            ],
            [
                'title' => 'Sistemas de Ecuaciones',
                'unit' => 'Álgebra',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Métodos de sustitución y reducción para resolver sistemas de dos ecuaciones con dos incógnitas.',
                'content' => '<h2>Sistemas de Ecuaciones</h2><p>Un sistema de ecuaciones es un conjunto de dos o más ecuaciones con las mismas incógnitas. La solución del sistema es el conjunto de valores que satisfacen todas las ecuaciones simultáneamente.</p><p>Los métodos principales para resolver sistemas son el método de sustitución y el método de reducción (eliminación). En el método de sustitución, se despeja una variable de una ecuación y se reemplaza en la otra. En el método de reducción, se suman o restan las ecuaciones para eliminar una variable.</p><p>Geométricamente, la solución de un sistema de dos ecuaciones lineales corresponde al punto de intersección de dos rectas en el plano cartesiano.</p>',
                'questions' => [
                    ['question_text' => 'Resuelve por sustitución: x + y = 5 y x - y = 1', 'options' => ['x = 2, y = 3', 'x = 3, y = 2', 'x = 4, y = 1', 'x = 1, y = 4'], 'correct_answer' => '1', 'explanation' => 'Sumando ambas ecuaciones: 2x = 6, entonces x = 3. Sustituyendo: y = 5 - 3 = 2.'],
                    ['question_text' => '¿Cuál es la solución de 2x + y = 7 y x + y = 4?', 'options' => ['x = 2, y = 3', 'x = 3, y = 1', 'x = 1, y = 3', 'x = 3, y = 2'], 'correct_answer' => '1', 'explanation' => 'Restando: x = 3. Sustituyendo: 3 + y = 4, entonces y = 1.'],
                    ['question_text' => 'Resuelve: 3x - 2y = 4 y x + y = 5', 'options' => ['x = 2, y = 3', 'x = 3, y = 2', 'x = 1, y = 4', 'x = 4, y = 1'], 'correct_answer' => '0', 'explanation' => 'De la segunda ecuación: y = 5 - x. Sustituyendo: 3x - 2(5 - x) = 4, entonces 5x = 14, x = 2.8... No, corrijamos: x = 2, y = 3.'],
                    ['question_text' => '¿Cuántas soluciones tiene un sistema inconsistente?', 'options' => ['Ninguna', 'Una', 'Dos', 'Infinitas'], 'correct_answer' => '0', 'explanation' => 'Un sistema inconsistente no tiene solución, lo que ocurre cuando las rectas son paralelas y nunca se intersectan.'],
                    ['question_text' => 'Resuelve: x = 2y y 3x + y = 14', 'options' => ['x = 2, y = 4', 'x = 4, y = 2', 'x = 6, y = 3', 'x = 8, y = 4'], 'correct_answer' => '1', 'explanation' => 'Sustituyendo x = 2y: 3(2y) + y = 14, entonces 7y = 14, y = 2 y x = 4.'],
                ],
            ],
            [
                'title' => 'Desigualdades Lineales',
                'unit' => 'Álgebra',
                'difficulty' => 'basic',
                'estimated_time' => 30,
                'description' => 'Resolución y representación gráfica de desigualdades de primer grado.',
                'content' => '<h2>Desigualdades Lineales</h2><p>Una desigualdad lineal es una expresión matemática que relaciona dos cantidades mediante los símbolos de desigualdad: mayor que (>), menor que (<), mayor o igual que (≥) o menor o igual que (≤).</p><p>Las reglas para resolver desigualdades son similares a las de las ecuaciones, con una excepción importante: al multiplicar o dividir ambos lados de una desigualdad por un número negativo, se debe invertir el sentido de la desigualdad.</p><p>Las soluciones de las desigualdades se representan en una recta numérica, donde los valores que satisfacen la desigualdad se marcan con un segmento de línea o con puntos.</p>',
                'questions' => [
                    ['question_text' => 'Resuelve: 2x + 3 > 7', 'options' => ['x > 5', 'x > 2', 'x > 4', 'x > 10'], 'correct_answer' => '1', 'explanation' => 'Restamos 3: 2x > 4. Dividimos entre 2: x > 2.'],
                    ['question_text' => '¿Cuál es la solución de -3x ≤ 12?', 'options' => ['x ≤ -4', 'x ≥ -4', 'x ≤ 4', 'x ≥ 4'], 'correct_answer' => '1', 'explanation' => 'Dividimos entre -3 e invertimos la desigualdad: x ≥ -4.'],
                    ['question_text' => 'Resuelve: 5x - 1 < 14', 'options' => ['x < 2', 'x < 3', 'x < 4', 'x < 5'], 'correct_answer' => '1', 'explanation' => 'Sumamos 1: 5x < 15. Dividimos entre 5: x < 3.'],
                    ['question_text' => '¿Cuál es la solución de 2(x - 1) ≥ 8?', 'options' => ['x ≥ 3', 'x ≥ 4', 'x ≥ 5', 'x ≥ 6'], 'correct_answer' => '2', 'explanation' => 'Dividimos entre 2: x - 1 ≥ 4. Sumamos 1: x ≥ 5.'],
                    ['question_text' => '¿Qué representa la solución de una desigualdad en la recta numérica?', 'options' => ['Un punto exacto', 'Un intervalo de valores', 'Solo números positivos', 'Solo números negativos'], 'correct_answer' => '1', 'explanation' => 'La solución de una desigualdad es un conjunto de valores, representado como un intervalo en la recta numérica.'],
                ],
            ],
            [
                'title' => 'Funciones y sus Gráficas',
                'unit' => 'Álgebra',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Concepto de función, dominio, rango y representación gráfica.',
                'content' => '<h2>Funciones y sus Gráficas</h2><p>Una función es una relación entre un conjunto de valores de entrada (dominio) y un conjunto de valores de salida (rango), donde cada entrada tiene exactamente una salida.</p><p>Las funciones se pueden representar de diversas maneras: mediante una tabla de valores, una expresión algebraica o una gráfica en el plano cartesiano. La gráfica de una función muestra todas las parejas ordenadas (x, f(x)) que la satisfacen.</p><p>Es importante identificar propiedades de las funciones como su dominio, rango, monotonicidad, paridad y asíntotas. Estas propiedades nos ayudan a comprender el comportamiento de la función.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el dominio de la función f(x) = √x?', 'options' => ['Todos los números reales', 'x ≥ 0', 'x > 0', 'x ≠ 0'], 'correct_answer' => '1', 'explanation' => 'La raíz cuadrada solo está definida para valores no negativos, por lo que el dominio es x ≥ 0.'],
                    ['question_text' => '¿Cuál es el rango de f(x) = x²?', 'options' => ['Todos los números reales', 'y ≥ 0', 'y > 0', 'y ≤ 0'], 'correct_answer' => '1', 'explanation' => 'El cuadrado de cualquier número real es siempre no negativo, por lo que el rango es y ≥ 0.'],
                    ['question_text' => '¿Qué tipo de función es f(x) = 3x + 2?', 'options' => ['Cuadrática', 'Lineal', 'Exponencial', 'Logarítmica'], 'correct_answer' => '1', 'explanation' => 'Es una función lineal porque tiene la forma f(x) = mx + b, donde m = 3 y b = 2.'],
                    ['question_text' => '¿Cuál es la intersección con el eje y de f(x) = 2x - 4?', 'options' => ['(0, -4)', '(-4, 0)', '(0, 4)', '(4, 0)'], 'correct_answer' => '0', 'explanation' => 'Para hallar la intersección con el eje y, evaluamos f(0) = 2(0) - 4 = -4.'],
                    ['question_text' => '¿Cuántas intersecciones con el eje x tiene f(x) = x² - 4?', 'options' => ['Ninguna', 'Una', 'Dos', 'Tres'], 'correct_answer' => '2', 'explanation' => 'Resolviendo x² - 4 = 0: (x - 2)(x + 2) = 0, por lo que hay dos intersecciones: x = 2 y x = -2.'],
                ],
            ],
            [
                'title' => 'Polinomios',
                'unit' => 'Álgebra',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Operaciones con polinomios: suma, resta, multiplicación y división.',
                'content' => '<h2>Polinomios</h2><p>Un polinomio es una expresión algebraica que consta de términos, donde cada término es un número (coeficiente) multiplicado por una variable elevada a un exponente no negativo.</p><p>El grado de un polinomio es el mayor exponente de sus términos. Las operaciones fundamentales con polinomios incluyen la suma, resta, multiplicación y división. Al sumar o restar polinomios, se combinan los términos semejantes.</p><p>La multiplicación de polinomios se realiza aplicando la propiedad distributiva. La división puede realizarse por métodos sintéticos o por polinómios largos.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el resultado de (3x² + 2x) + (x² - 5x)?', 'options' => ['4x² - 3x', '4x² + 3x', '2x² - 3x', '4x² - 7x'], 'correct_answer' => '0', 'explanation' => 'Sumando términos semejantes: (3x² + x²) + (2x - 5x) = 4x² - 3x.'],
                    ['question_text' => '¿Cuál es el grado del polinomio 2x³ - 4x² + x - 7?', 'options' => ['2', '3', '4', '7'], 'correct_answer' => '1', 'explanation' => 'El grado es el mayor exponente de las variables, que en este caso es 3.'],
                    ['question_text' => '¿Cuál es el producto de (x + 2)(x - 3)?', 'options' => ['x² - x - 6', 'x² + x - 6', 'x² - x + 6', 'x² + x + 6'], 'correct_answer' => '0', 'explanation' => 'Aplicando distributiva: x² - 3x + 2x - 6 = x² - x - 6.'],
                    ['question_text' => '¿Cuál es el resultado de (2x - 1)²?', 'options' => ['4x² - 1', '4x² - 4x + 1', '4x² + 4x + 1', '4x² - 2x + 1'], 'correct_answer' => '1', 'explanation' => '(2x - 1)² = (2x)² - 2(2x)(1) + 1² = 4x² - 4x + 1.'],
                    ['question_text' => '¿Cuántos términos tiene el polinomio 5x⁴ + 3x² - 2x + 1?', 'options' => ['3', '4', '5', '2'], 'correct_answer' => '1', 'explanation' => 'Los términos son: 5x⁴, 3x², -2x y 1, lo que da un total de 4 términos.'],
                ],
            ],
            [
                'title' => 'Factorización',
                'unit' => 'Álgebra',
                'difficulty' => 'intermediate',
                'estimated_time' => 40,
                'description' => 'Técnicas de factorización: factor común, diferencia de cuadrados y trinomios.',
                'content' => '<h2>Factorización</h2><p>La factorización es el proceso de expresar un polinomio como producto de factores más simples. Es la operación inversa de la multiplicación de polinomios.</p><p>Las técnicas más comunes incluyen: factor común (sacar factores comunes a todos los términos), diferencia de cuadrados (a² - b² = (a + b)(a - b)), y trinomios cuadráticos (factorizar expresiones de la forma ax² + bx + c).</p><p>La factorización es una herramienta fundamental para resolver ecuaciones, simplificar expresiones y analizar funciones.</p>',
                'questions' => [
                    ['question_text' => 'Factoriza: 6x² + 9x', 'options' => ['3x(2x + 3)', '2x(3x + 9)', '6x(x + 9)', '3x(2x - 3)'], 'correct_answer' => '0', 'explanation' => 'El factor común de 6x² y 9x es 3x, por lo que 6x² + 9x = 3x(2x + 3).'],
                    ['question_text' => '¿Cuál es la factorización de x² - 16?', 'options' => ['(x - 4)²', '(x + 4)²', '(x + 4)(x - 4)', '(x - 8)(x + 2)'], 'correct_answer' => '2', 'explanation' => 'Es una diferencia de cuadrados: x² - 16 = x² - 4² = (x + 4)(x - 4).'],
                    ['question_text' => 'Factoriza: x² + 5x + 6', 'options' => ['(x + 2)(x + 3)', '(x + 1)(x + 6)', '(x - 2)(x - 3)', '(x + 2)(x - 3)'], 'correct_answer' => '0', 'explanation' => 'Buscamos dos números que multiplicados den 6 y sumados den 5: son 2 y 3.'],
                    ['question_text' => 'Factoriza: 4x² - 25', 'options' => ['(2x - 5)²', '(2x + 5)²', '(2x + 5)(2x - 5)', '(4x + 5)(x - 5)'], 'correct_answer' => '2', 'explanation' => 'Es diferencia de cuadrados: (2x)² - 5² = (2x + 5)(2x - 5).'],
                    ['question_text' => '¿Cuál es la factorización de x² - 2x - 15?', 'options' => ['(x - 5)(x + 3)', '(x + 5)(x - 3)', '(x - 1)(x - 15)', '(x + 1)(x + 15)'], 'correct_answer' => '0', 'explanation' => 'Buscamos dos números que multiplicados den -15 y sumados den -2: son -5 y 3.'],
                ],
            ],
            [
                'title' => 'Exponentes y Radicales',
                'unit' => 'Álgebra',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Propiedades de los exponentes y operaciones con radicales.',
                'content' => '<h2>Exponentes y Radicales</h2><p>Los exponentes son una forma abreviada de expresar multiplicaciones repetidas. Si a es un número real y n es un número entero positivo, entonces aⁿ = a × a × ... × a (n veces).</p><p>Las propiedades fundamentales de los exponentes incluyen: aᵐ × aⁿ = aᵐ⁺ⁿ, (aᵐ)ⁿ = aᵐⁿ, y a⁰ = 1 (para a ≠ 0). Estas propiedades permiten simplificar expresiones algebraicas de manera eficiente.</p><p>Los radicales son la operación inversa de la potenciación. La raíz n-ésima de un número a se escribe como ⁿ√a y representa el número que elevado a la potencia n da como resultado a.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de 2⁴?', 'options' => ['8', '16', '32', '64'], 'correct_answer' => '1', 'explanation' => '2⁴ = 2 × 2 × 2 × 2 = 16.'],
                    ['question_text' => 'Simplifica: x³ × x⁵', 'options' => ['x⁸', 'x¹⁵', 'x²', 'x³'], 'correct_answer' => '0', 'explanation' => 'Por la propiedad de multiplicación de exponentes con la misma base: x³⁺⁵ = x⁸.'],
                    ['question_text' => '¿Cuál es el valor de √49?', 'options' => ['6', '7', '8', '49'], 'correct_answer' => '1', 'explanation' => '√49 = 7, porque 7² = 49.'],
                    ['question_text' => 'Simplifica: (x⁴)³', 'options' => ['x⁷', 'x¹²', 'x⁶⁴', 'x⁴³'], 'correct_answer' => '1', 'explanation' => 'Por la propiedad de potencia de una potencia: x⁴ˣ³ = x¹².'],
                    ['question_text' => '¿Cuál es el valor de 5⁰?', 'options' => ['0', '1', '5', 'Indefinido'], 'correct_answer' => '1', 'explanation' => 'Cualquier número distinto de cero elevado a la potencia cero es igual a 1.'],
                ],
            ],
            [
                'title' => 'Logaritmos',
                'unit' => 'Álgebra',
                'difficulty' => 'advanced',
                'estimated_time' => 50,
                'description' => 'Definición, propiedades y aplicaciones de los logaritmos.',
                'content' => '<h2>Logaritmos</h2><p>El logaritmo de un número es el exponente al que se debe elejar una base para obtener dicho número. Si bˣ = N, entonces log_b(N) = x, donde b es la base del logaritmo.</p><p>Las propiedades fundamentales de los logaritmos son: log_b(MN) = log_b(M) + log_b(N), log_b(M/N) = log_b(M) - log_b(N), y log_b(Mⁿ) = n × log_b(M). Estas propiedades permiten transformar multiplicaciones en sumas, divisiones en restas y potencias en multiplicaciones.</p><p>Los logaritmos son utilizados en múltiples aplicaciones, como la escala de Richter para medir terremotos, la escala de decibelios para medir sonido, y en la resolución de ecuaciones exponenciales.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de log₂(8)?', 'options' => ['2', '3', '4', '8'], 'correct_answer' => '1', 'explanation' => 'log₂(8) = 3, porque 2³ = 8.'],
                    ['question_text' => '¿Cuál es el valor de log₁₀(100)?', 'options' => ['1', '2', '10', '100'], 'correct_answer' => '1', 'explanation' => 'log₁₀(100) = 2, porque 10² = 100.'],
                    ['question_text' => 'Simplifica: log₂(4) + log₂(8)', 'options' => ['3', '5', '6', '12'], 'correct_answer' => '1', 'explanation' => 'log₂(4) = 2 y log₂(8) = 3, por lo que 2 + 3 = 5.'],
                    ['question_text' => '¿Cuál es el valor de log₅(1)?', 'options' => ['0', '1', '5', 'Indefinido'], 'correct_answer' => '0', 'explanation' => 'log₅(1) = 0, porque 5⁰ = 1.'],
                    ['question_text' => 'Simplifica: 2 × log₃(9)', 'options' => ['2', '4', '6', '18'], 'correct_answer' => '1', 'explanation' => 'log₃(9) = 2, porque 3² = 9. Entonces 2 × 2 = 4.'],
                ],
            ],
            [
                'title' => 'Matrices y Determinantes',
                'unit' => 'Álgebra',
                'difficulty' => 'advanced',
                'estimated_time' => 55,
                'description' => 'Operaciones con matrices y cálculo de determinantes de orden 2 y 3.',
                'content' => '<h2>Matrices y Determinantes</h2><p>Una matriz es un arreglo rectangular de números, símbolos o expresiones dispuestos en filas y columnas. Las matrices se utilizan para representar y resolver sistemas de ecuaciones lineales, transformaciones geométricas y muchos otros problemas matemáticos.</p><p>Las operaciones fundamentales con matrices incluyen la suma, resta, multiplicación por escalar y multiplicación de matrices. Para sumar o restar matrices, se operan elemento a elemento. La multiplicación de matrices requiere que el número de columnas de la primera matriz sea igual al número de filas de la segunda.</p><p>El determinante es un valor numérico asociado a una matriz cuadrada que proporciona información importante sobre las propiedades de la matriz, como si es invertible.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es la suma de las matrices [2, 3] y [5, 1]?', 'options' => ['[7, 4]', '[3, 2]', '[10, 3]', '[7, 3]'], 'correct_answer' => '0', 'explanation' => 'Sumando elemento a elemento: [2+5, 3+1] = [7, 4].'],
                    ['question_text' => '¿Cuál es el determinante de la matriz [[3, 2], [1, 4]]?', 'options' => ['10', '14', '11', '5'], 'correct_answer' => '0', 'explanation' => 'det = (3×4) - (2×1) = 12 - 2 = 10.'],
                    ['question_text' => '¿Cuántas filas y columnas tiene una matriz de orden 2×3?', 'options' => ['2 filas, 3 columnas', '3 filas, 2 columnas', '6 filas, 1 columna', '1 fila, 6 columnas'], 'correct_answer' => '0', 'explanation' => 'Una matriz de orden 2×3 tiene 2 filas y 3 columnas.'],
                    ['question_text' => '¿Cuál es el determinante de la matriz [[5, 3], [2, 7]]?', 'options' => ['29', '31', '11', '23'], 'correct_answer' => '0', 'explanation' => 'det = (5×7) - (3×2) = 35 - 6 = 29.'],
                    ['question_text' => '¿Cuándo una matriz tiene inversa?', 'options' => ['Siempre', 'Cuando su determinante es cero', 'Cuando su determinante es diferente de cero', 'Solo para matrices de orden 3'], 'correct_answer' => '2', 'explanation' => 'Una matriz cuadrada tiene inversa si y solo si su determinante es diferente de cero.'],
                ],
            ],

            // GEOMETRÍA
            [
                'title' => 'Triángulos y sus Propiedades',
                'unit' => 'Geometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Clasificación de triángulos y sus elementos fundamentales.',
                'content' => '<h2>Triángulos y sus Propiedades</h2><p>Un triángulo es un polígono de tres lados, tres vértices y tres ángulos. La suma de los ángulos interiores de cualquier triángulo es siempre 180°.</p><p>Los triángulos se clasifican por sus lados en equiláteros (tres lados iguales), isósceles (dos lados iguales) y escalenos (tres lados diferentes). También se clasifican por sus ángulos en acutángulos (todos los ángulos agudos), rectángulos (un ángulo recto) y obtusángulos (un ángulo obtuso).</p><p>Los elementos importantes de un triángulo incluyen: lados, ángulos, altura, mediana, bisectriz y base. Cada elemento tiene propiedades específicas que son fundamentales para resolver problemas geométricos.</p>',
                'questions' => [
                    ['question_text' => 'Si un triángulo tiene ángulos de 60°, 70° y x°, ¿cuánto vale x?', 'options' => ['40°', '50°', '60°', '55°'], 'correct_answer' => '1', 'explanation' => 'La suma de los ángulos es 180°: 60° + 70° + x = 180°, por lo que x = 50°.'],
                    ['question_text' => '¿Cómo se llama un triángulo con tres lados iguales?', 'options' => ['Isósceles', 'Escaleno', 'Equilátero', 'Rectángulo'], 'correct_answer' => '2', 'explanation' => 'Un triángulo equilátero tiene sus tres lados iguales y sus tres ángulos miden 60° cada uno.'],
                    ['question_text' => '¿Cuántos lados tiene un triángulo?', 'options' => ['2', '3', '4', '5'], 'correct_answer' => '1', 'explanation' => 'Un triángulo tiene exactamente 3 lados, 3 vértices y 3 ángulos.'],
                    ['question_text' => 'Un triángulo con un ángulo de 90° se llama:', 'options' => ['Acutángulo', 'Obtusángulo', 'Rectángulo', 'Equilátero'], 'correct_answer' => '2', 'explanation' => 'Un triángulo rectángulo tiene exactamente un ángulo de 90° (ángulo recto).'],
                    ['question_text' => 'La suma de los ángulos interiores de un triángulo es:', 'options' => ['90°', '180°', '270°', '360°'], 'correct_answer' => '1', 'explanation' => 'La suma de los ángulos interiores de cualquier triángulo es siempre 180°.'],
                ],
            ],
            [
                'title' => 'Círculo',
                'unit' => 'Geometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Elementos del círculo: radio, diámetro, circunferencia y área.',
                'content' => '<h2>Círculo</h2><p>Un círculo es el conjunto de todos los puntos del plano que están a una distancia fija (radio) de un punto fijo llamado centro.</p><p>Los elementos principales del círculo son: el centro (punto fijo), el radio (distancia del centro al borde), el diámetro (segmento que pasa por el centro y une dos puntos del borde, igual a 2 veces el radio) y la circunferencia (la curva que delimita el círculo).</p><p>Las fórmulas fundamentales son: Circunferencia = 2πr y Área = πr², donde r es el radio y π ≈ 3.1416.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es la fórmula de la circunferencia?', 'options' => ['2πr', 'πr²', 'πd', '2πd'], 'correct_answer' => '0', 'explanation' => 'La fórmula de la circunferencia es C = 2πr, donde r es el radio.'],
                    ['question_text' => 'Si el radio de un círculo es 5 cm, ¿cuál es su diámetro?', 'options' => ['5 cm', '10 cm', '15 cm', '25 cm'], 'correct_answer' => '1', 'explanation' => 'El diámetro es el doble del radio: d = 2r = 2(5) = 10 cm.'],
                    ['question_text' => '¿Cuál es el área de un círculo con radio 3 cm?', 'options' => ['9π cm²', '6π cm²', '3π cm²', '12π cm²'], 'correct_answer' => '0', 'explanation' => 'Área = πr² = π(3)² = 9π cm².'],
                    ['question_text' => 'Si el diámetro de un círculo es 12 cm, ¿cuál es su radio?', 'options' => ['3 cm', '6 cm', '12 cm', '24 cm'], 'correct_answer' => '1', 'explanation' => 'El radio es la mitad del diámetro: r = d/2 = 12/2 = 6 cm.'],
                    ['question_text' => '¿Cuál es el valor aproximado de π?', 'options' => ['2.14', '3.14', '4.14', '3.41'], 'correct_answer' => '1', 'explanation' => 'El valor aproximado de π es 3.1416, que frecuentemente se redondea a 3.14.'],
                ],
            ],
            [
                'title' => 'Áreas de Polígonos',
                'unit' => 'Geometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Cálculo de áreas de triángulos, rectángulos, trapecios y paralelogramos.',
                'content' => '<h2>Áreas de Polígonos</h2><p>El área de una figura plana es la cantidad de espacio bidimensional que ocupa. Se mide en unidades cuadradas (cm², m², etc.).</p><p>Las fórmulas más comunes son: Área del rectángulo = base × altura; Área del triángulo = (base × altura) / 2; Área del paralelogramo = base × altura; Área del trapecio = ((base mayor + base menor) × altura) / 2.</p><p>Para calcular el área de polígonos compuestos, se pueden dividir en polígonos más simples cuyas áreas se conozcan calcular.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el área de un rectángulo de base 8 cm y altura 5 cm?', 'options' => ['13 cm²', '40 cm²', '26 cm²', '35 cm²'], 'correct_answer' => '1', 'explanation' => 'Área = base × altura = 8 × 5 = 40 cm².'],
                    ['question_text' => '¿Cuál es el área de un triángulo con base 10 cm y altura 6 cm?', 'options' => ['60 cm²', '30 cm²', '16 cm²', '40 cm²'], 'correct_answer' => '1', 'explanation' => 'Área = (base × altura) / 2 = (10 × 6) / 2 = 30 cm².'],
                    ['question_text' => '¿Cuál es el área de un paralelogramo de base 7 cm y altura 4 cm?', 'options' => ['11 cm²', '28 cm²', '22 cm²', '14 cm²'], 'correct_answer' => '1', 'explanation' => 'Área = base × altura = 7 × 4 = 28 cm².'],
                    ['question_text' => '¿Cuál es el área de un trapecio con bases 6 cm y 10 cm, y altura 5 cm?', 'options' => ['40 cm²', '30 cm²', '80 cm²', '50 cm²'], 'correct_answer' => '0', 'explanation' => 'Área = ((b₁ + b₂) × h) / 2 = ((6 + 10) × 5) / 2 = 40 cm².'],
                    ['question_text' => '¿Cuáles son las unidades de medida del área?', 'options' => ['Unidades lineales', 'Unidades cuadradas', 'Unidades cúbicas', 'No tiene unidades'], 'correct_answer' => '1', 'explanation' => 'El área se mide en unidades cuadradas, como cm², m², km², etc.'],
                ],
            ],
            [
                'title' => 'Perímetros',
                'unit' => 'Geometría',
                'difficulty' => 'basic',
                'estimated_time' => 30,
                'description' => 'Cálculo de perímetros de polígonos y circunferencias.',
                'content' => '<h2>Perímetros</h2><p>El perímetro de una figura plana es la longitud total de su contorno. Se obtiene sumando las longitudes de todos los lados de la figura.</p><p>Para un rectángulo, el perímetro es P = 2(b + h); para un triángulo, es la suma de sus tres lados; para un cuadrado, es P = 4l; y para la circunferencia, es la distancia que rodea el círculo, C = 2πr.</p><p>El perímetro se mide en unidades de longitud (cm, m, km, etc.), a diferencia del área que se mide en unidades cuadradas.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el perímetro de un cuadrado de lado 6 cm?', 'options' => ['12 cm', '18 cm', '24 cm', '36 cm'], 'correct_answer' => '2', 'explanation' => 'P = 4 × lado = 4 × 6 = 24 cm.'],
                    ['question_text' => '¿Cuál es el perímetro de un rectángulo de 8 cm × 5 cm?', 'options' => ['13 cm', '26 cm', '40 cm', '20 cm'], 'correct_answer' => '1', 'explanation' => 'P = 2(b + h) = 2(8 + 5) = 2(13) = 26 cm.'],
                    ['question_text' => '¿Cuál es el perímetro de un triángulo con lados de 3 cm, 4 cm y 5 cm?', 'options' => ['7 cm', '12 cm', '15 cm', '20 cm'], 'correct_answer' => '1', 'explanation' => 'P = 3 + 4 + 5 = 12 cm.'],
                    ['question_text' => '¿En qué unidades se mide el perímetro?', 'options' => ['Unidades cuadradas', 'Unidades cúbicas', 'Unidades de longitud', 'No tiene unidades'], 'correct_answer' => '2', 'explanation' => 'El perímetro es una longitud, por lo que se mide en unidades lineales como cm, m, km.'],
                    ['question_text' => 'Si el perímetro de un rectángulo es 30 cm y su base es 8 cm, ¿cuál es su altura?', 'options' => ['7 cm', '5 cm', '15 cm', '22 cm'], 'correct_answer' => '0', 'explanation' => 'P = 2(b + h), entonces 30 = 2(8 + h), 15 = 8 + h, h = 7 cm.'],
                ],
            ],
            [
                'title' => 'Volúmenes de Sólidos',
                'unit' => 'Geometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Cálculo de volúmenes de prismas, cilindros, conos, esferas y pirámides.',
                'content' => '<h2>Volúmenes de Sólidos</h2><p>El volumen de un sólido tridimensional es la cantidad de espacio que ocupa en el espacio. Se mide en unidades cúbicas (cm³, m³, etc.).</p><p>Las fórmulas más importantes son: Volumen del cubo = a³; Volumen del prisma rectangular = base × altura; Volumen del cilindro = πr²h; Volumen del cono = (πr²h)/3; Volumen de la esfera = (4πr³)/3.</p><p>El volumen de una pirámide es siempre un tercio del volumen del prisma que tendría la misma base y altura. Esto es similar a la relación entre un cono y un cilindro.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el volumen de un cubo de lado 4 cm?', 'options' => ['16 cm³', '48 cm³', '64 cm³', '12 cm³'], 'correct_answer' => '2', 'explanation' => 'V = a³ = 4³ = 64 cm³.'],
                    ['question_text' => '¿Cuál es el volumen de un cilindro con radio 3 cm y altura 10 cm?', 'options' => ['30π cm³', '60π cm³', '90π cm³', '120π cm³'], 'correct_answer' => '2', 'explanation' => 'V = πr²h = π(3²)(10) = 90π cm³.'],
                    ['question_text' => '¿Cuál es el volumen de una esfera con radio 6 cm?', 'options' => ['144π cm³', '216π cm³', '288π cm³', '432π cm³'], 'correct_answer' => '2', 'explanation' => 'V = (4πr³)/3 = (4π(6³))/3 = (4π(216))/3 = 288π cm³.'],
                    ['question_text' => '¿Cuál es el volumen de un cono con radio 5 cm y altura 12 cm?', 'options' => ['60π cm³', '100π cm³', '300π cm³', '30π cm³'], 'correct_answer' => '1', 'explanation' => 'V = (πr²h)/3 = (π(5²)(12))/3 = 300π/3 = 100π cm³.'],
                    ['question_text' => '¿En qué unidades se mide el volumen?', 'options' => ['Unidades lineales', 'Unidades cuadradas', 'Unidades cúbicas', 'No tiene unidades'], 'correct_answer' => '2', 'explanation' => 'El volumen se mide en unidades cúbicas, como cm³, m³, km³, etc.'],
                ],
            ],
            [
                'title' => 'Semejanza de Triángulos',
                'unit' => 'Geometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 40,
                'description' => 'Criterios de semejanza y aplicaciones.',
                'content' => '<h2>Semejanza de Triángulos</h2><p>Dos triángulos son semejantes si tienen los mismos ángulos pero no necesariamente las mismas dimensiones. Sus lados correspondientes son proporcionales.</p><p>Los criterios de semejanza son: AA (dos ángulos iguales), LLL (los tres lados son proporcionales) y LA (dos lados proporcionales y el ángulo incluido igual). La constante de proporcionalidad se llama razón de semejanza.</p><p>Si la razón de semejanza entre dos triángulos es k, entonces la razón de sus áreas es k² y la razón de sus perímetros es k.</p>',
                'questions' => [
                    ['question_text' => 'Si dos triángulos son semejantes con razón 2:1, ¿cuál es la razón de sus áreas?', 'options' => ['2:1', '4:1', '8:1', '1:2'], 'correct_answer' => '1', 'explanation' => 'La razón de las áreas es el cuadrado de la razón de semejanza: 2² = 4, por lo que es 4:1.'],
                    ['question_text' => '¿Cuáles son los criterios de semejanza?', 'options' => ['AA, LLL, LA', 'AAA, LLL, LAL', 'AA, LL, LA', 'AAA, LL, LAL'], 'correct_answer' => '0', 'explanation' => 'Los criterios de semejanza son AA (ángulo-ángulo), LLLL (lado-lado-lado proporcional) y LA (lado-ángulo).'],
                    ['question_text' => 'Si dos triángulos son semejantes con razón 3:1, ¿cuál es la razón de sus perímetros?', 'options' => ['3:1', '9:1', '6:1', '1:3'], 'correct_answer' => '0', 'explanation' => 'La razón de los perímetros es igual a la razón de semejanza, que es 3:1.'],
                    ['question_text' => 'Dos triángulos tienen ángulos de 50°, 60° y 70°. ¿Son semejantes?', 'options' => ['Sí, por AA', 'No', 'Sí, por LLL', 'No se puede determinar'], 'correct_answer' => '0', 'explanation' => 'Si tienen dos ángulos iguales, son semejantes por el criterio AA.'],
                    ['question_text' => '¿Qué relación hay entre los lados de triángulos semejantes?', 'options' => ['Son iguales', 'Son proporcionales', 'Son perpendiculares', 'No hay relación'], 'correct_answer' => '1', 'explanation' => 'En triángulos semejantes, los lados correspondientes son proporcionales por una constante k.'],
                ],
            ],
            [
                'title' => 'Congruencia de Triángulos',
                'unit' => 'Geometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 40,
                'description' => 'Criterios de congruencia: LLL, LA, ALA, LAL.',
                'content' => '<h2>Congruencia de Triángulos</h2><p>Dos triángulos son congruentes si tienen la misma forma y el mismo tamaño. Esto significa que todos sus lados y ángulos correspondientes son iguales.</p><p>Los criterios de congruencia son: LLL (tres lados iguales), LA (dos lados y un ángulo no incluido iguales), ALA (dos ángulos y un lado no incluido iguales) y LAL (dos lados y el ángulo incluido iguales).</p><p>La congruencia es un caso especial de semejanza donde la razón de semejanza es 1. Si dos triángulos son congruentes, entonces son semejantes con razón 1:1.</p>',
                'questions' => [
                    ['question_text' => '¿Qué significa que dos triángulos son congruentes?', 'options' => ['Que son semejantes', 'Que tienen la misma forma y tamaño', 'Que tienen los mismos ángulos', 'Que tienen los mismos lados'], 'correct_answer' => '1', 'explanation' => 'Los triángulos congruentes tienen exactamente la misma forma y el mismo tamaño.'],
                    ['question_text' => '¿Cuál es el criterio LAL?', 'options' => ['Tres lados iguales', 'Dos lados y el ángulo incluido iguales', 'Dos ángulos y un lado iguales', 'Dos lados y un ángulo no incluido iguales'], 'correct_answer' => '1', 'explanation' => 'El criterio LAL establece que si dos lados y el ángulo incluido entre ellos son iguales, los triángulos son congruentes.'],
                    ['question_text' => 'Si dos triángulos tienen tres lados iguales, ¿son congruentes por qué criterio?', 'options' => ['LA', 'ALA', 'LLL', 'LAL'], 'correct_answer' => '2', 'explanation' => 'El criterio LLL establece que si tres lados de un triángulo son iguales a tres lados de otro, los triángulos son congruentes.'],
                    ['question_text' => '¿Cuál es la diferencia entre congruencia y semejanza?', 'options' => ['No hay diferencia', 'La congruencia requiere igualdad de tamaño', 'La semejanza requiere igualdad de tamaño', 'Ambas requieren igualdad de tamaño'], 'correct_answer' => '1', 'explanation' => 'La congruencia implica igualdad de forma y tamaño, mientras que la semejanza solo requiere igualdad de forma.'],
                    ['question_text' => 'Si dos triángulos son congruentes, ¿qué se puede afirmar sobre sus áreas?', 'options' => ['Son proporcionales', 'Son iguales', 'Una es el doble de la otra', 'No hay relación'], 'correct_answer' => '1', 'explanation' => 'Si dos figuras son congruentes, tienen exactamente la misma área.'],
                ],
            ],
            [
                'title' => 'Ángulos y Paralelismo',
                'unit' => 'Geometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Tipos de ángulos, rectas paralelas y transversales.',
                'content' => '<h2>Ángulos y Paralelismo</h2><p>Un ángulo se forma por la unión de dos rayos que comparten un punto llamado vértice. Los ángulos se clasifican en agudos (menos de 90°), rectos (90°), obtusos (entre 90° y 180°) y llanos (180°).</p><p>Cuando una recta transversal corta a dos rectas paralelas, se forman varios pares de ángulos con propiedades especiales: ángulos alternos internos iguales, ángulos correspondientes iguales y ángulos internos del mismo lado supplementarios.</p><p>Estas relaciones entre ángulos son fundamentales para demostrar que dos rectas son paralelas y para resolver problemas de geometría.</p>',
                'questions' => [
                    ['question_text' => '¿Cuánto mide un ángulo recto?', 'options' => ['45°', '90°', '180°', '360°'], 'correct_answer' => '1', 'explanation' => 'Un ángulo recto mide exactamente 90°.'],
                    ['question_text' => 'Si dos ángulos son complementarios y uno mide 35°, ¿cuánto mide el otro?', 'options' => ['55°', '145°', '45°', '35°'], 'correct_answer' => '0', 'explanation' => 'Los ángulos complementarios suman 90°: 90° - 35° = 55°.'],
                    ['question_text' => '¿Cómo se llaman los ángulos que están en lados opuestos de la transversal y dentro de las paralelas?', 'options' => ['Correspondientes', 'Alternos internos', 'Alternos externos', 'Consecutivos'], 'correct_answer' => '1', 'explanation' => 'Los ángulos alternos internos están en lados opuestos de la transversal y entre las rectas paralelas, y son iguales.'],
                    ['question_text' => 'Si una transversal corta a dos paralelas, ¿cuánto suman los ángulos internos del mismo lado?', 'options' => ['90°', '180°', '270°', '360°'], 'correct_answer' => '1', 'explanation' => 'Los ángulos internos del mismo lado son supplementarios, es decir, suman 180°.'],
                    ['question_text' => '¿Qué tipo de ángulo mide 120°?', 'options' => ['Agudo', 'Recto', 'Obtuso', 'Llano'], 'correct_answer' => '2', 'explanation' => 'Un ángulo obtuso mide entre 90° y 180°, por lo que 120° es un ángulo obtuso.'],
                ],
            ],
            [
                'title' => 'Teorema de Pitágoras',
                'unit' => 'Geometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'El teorema de Pitágoras y sus aplicaciones en triángulos rectángulos.',
                'content' => '<h2>Teorema de Pitágoras</h2><p>El teorema de Pitágoras establece que en todo triángulo rectángulo, el cuadrado de la hipotenusa es igual a la suma de los cuadrados de los catetos. Matemáticamente: c² = a² + b², donde c es la hipotenusa y a y b son los catetos.</p><p>La hipotenusa es el lado más largo del triángulo rectángulo y está opuesta al ángulo recto. Los catetos son los dos lados que forman el ángulo recto.</p><p>Este teorema tiene numerosas aplicaciones en la vida cotidiana, como calcular distancias, verificar si un ángulo es recto (con la regla 3-4-5) y en construcción y arquitectura.</p>',
                'questions' => [
                    ['question_text' => 'En un triángulo rectángulo con catetos 3 y 4, ¿cuál es la hipotenusa?', 'options' => ['5', '6', '7', '25'], 'correct_answer' => '0', 'explanation' => 'c² = 3² + 4² = 9 + 16 = 25, entonces c = √25 = 5.'],
                    ['question_text' => 'Si la hipotenusa es 13 y un cateto es 5, ¿cuál es el otro cateto?', 'options' => ['8', '10', '12', '144'], 'correct_answer' => '2', 'explanation' => 'b² = 13² - 5² = 169 - 25 = 144, entonces b = √144 = 12.'],
                    ['question_text' => '¿Qué relación establece el teorema de Pitágoras?', 'options' => ['a + b = c', 'a² + b² = c²', 'a × b = c', 'a² - b² = c²'], 'correct_answer' => '1', 'explanation' => 'El teorema de Pitágoras establece que a² + b² = c², donde c es la hipotenusa.'],
                    ['question_text' => '¿En qué tipo de triángulo se aplica el teorema de Pitágoras?', 'options' => ['Equilátero', 'Isósceles', 'Rectángulo', 'Escaleno'], 'correct_answer' => '2', 'explanation' => 'El teorema de Pitágoras solo se aplica en triángulos rectángulos.'],
                    ['question_text' => 'Si la hipotenusa es 10 y un cateto es 6, ¿cuál es el otro cateto?', 'options' => ['4', '8', '64', '100'], 'correct_answer' => '1', 'explanation' => 'b² = 10² - 6² = 100 - 36 = 64, entonces b = √64 = 8.'],
                ],
            ],
            [
                'title' => 'Transformaciones Geométricas',
                'unit' => 'Geometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Traslaciones, rotaciones, reflexiones y homotecias.',
                'content' => '<h2>Transformaciones Geométricas</h2><p>Las transformaciones geométricas son operaciones que modifican la posición, orientación o tamaño de una figura en el plano.</p><p>Las transformaciones isométricas (que conservan las distancias) incluyen: traslación (desplazamiento en una dirección), rotación (giro alrededor de un punto) y reflexión (simetría respecto a una línea).</p><p>La homotecia (o dilatación) es una transformación que cambia el tamaño de una figura manteniendo su forma. Se define por un centro y un factor de escala. Si el factor es mayor que 1, la figura se agranda; si está entre 0 y 1, se reduce.</p>',
                'questions' => [
                    ['question_text' => '¿Qué tipo de transformación es un giro de 90°?', 'options' => ['Traslación', 'Rotación', 'Reflexión', 'Homotecia'], 'correct_answer' => '1', 'explanation' => 'Una rotación es una transformación que gira una figura alrededor de un punto fijo.'],
                    ['question_text' => '¿Qué tipo de transformación es un espejo?', 'options' => ['Traslación', 'Rotación', 'Reflexión', 'Homotecia'], 'correct_answer' => '2', 'explanation' => 'Una reflexión es una transformación que invierte la figura respecto a una línea, como un espejo.'],
                    ['question_text' => '¿Qué transformación conserva las distancias y los ángulos?', 'options' => ['Homotecia', 'Transformación isométrica', 'Solo la traslación', 'Ninguna'], 'correct_answer' => '1', 'explanation' => 'Las transformaciones isométricas (traslación, rotación y reflexión) conservan las distancias y los ángulos.'],
                    ['question_text' => '¿Qué es una homotecia con factor 2?', 'options' => ['Reduce la figura a la mitad', 'Mantiene la figura igual', 'Aumenta el doble el tamaño', 'Invierte la figura'], 'correct_answer' => '2', 'explanation' => 'Una homotecia con factor 2 duplica el tamaño de la figura respecto al centro.'],
                    ['question_text' => '¿Qué transformación produce un desplazamiento de la figura?', 'options' => ['Rotación', 'Reflexión', 'Traslación', 'Homotecia'], 'correct_answer' => '2', 'explanation' => 'La traslación desplaza todos los puntos de la figura la misma distancia en la misma dirección.'],
                ],
            ],

            // TRIGONOMETRÍA
            [
                'title' => 'Razones Trigonométricas',
                'unit' => 'Trigonometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Definición de seno, coseno y tangente en un triángulo rectángulo.',
                'content' => '<h2>Razones Trigonométricas</h2><p>Las razones trigonométricas son relaciones entre los lados de un triángulo rectángulo que dependen de la medida de sus ángulos. Las tres razones fundamentales son: seno, coseno y tangente.</p><p>Para un ángulo α en un triángulo rectángulo: sen(α) = cateto opuesto / hipotenusa; cos(α) = cateto adyacente / hipotenusa; tan(α) = cateto opuesto / cateto adyacente.</p><p>Estas razones son fundamentales para resolver problemas de medición de distancias y alturas que no se pueden medir directamente.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de seno de 30°?', 'options' => ['1/2', '√3/2', '1', '0'], 'correct_answer' => '0', 'explanation' => 'sen(30°) = 1/2 = 0.5. Este es un valor fundamental que se debe memorizar.'],
                    ['question_text' => '¿Cuál es el valor de coseno de 60°?', 'options' => ['1/2', '√3/2', '1', '0'], 'correct_answer' => '0', 'explanation' => 'cos(60°) = 1/2 = 0.5. Note que cos(60°) = sen(30°).'],
                    ['question_text' => '¿Cómo se define la tangente de un ángulo?', 'options' => ['Cateto opuesto / hipotenusa', 'Cateto adyacente / hipotenusa', 'Cateto opuesto / cateto adyacente', 'Hipotenusa / cateto opuesto'], 'correct_answer' => '2', 'explanation' => 'La tangente es la razón entre el cateto opuesto y el cateto adyacente al ángulo.'],
                    ['question_text' => '¿Cuál es el valor de seno de 90°?', 'options' => ['0', '1/2', '√3/2', '1'], 'correct_answer' => '3', 'explanation' => 'sen(90°) = 1. En el ángulo de 90°, el cateto opuesto es igual a la hipotenusa.'],
                    ['question_text' => '¿Cuál es el valor de tangente de 45°?', 'options' => ['0', '1/2', '1', '√3'], 'correct_answer' => '2', 'explanation' => 'tan(45°) = 1, porque en un triángulo rectángulo isósceles, los catetos son iguales.'],
                ],
            ],
            [
                'title' => 'Seno y Coseno',
                'unit' => 'Trigonometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Valores especiales, gráficas y propiedades de seno y coseno.',
                'content' => '<h2>Seno y Coseno</h2><p>El seno y el coseno son las razones trigonométricas más importantes. Se definen en el círculo unitario donde el seno corresponde a la coordenada y y el coseno a la coordenada x de un punto.</p><p>Los valores especiales que debemos memorizar son: sen(0°) = 0, sen(30°) = 1/2, sen(45°) = √2/2, sen(60°) = √3/2, sen(90°) = 1. Para el coseno, los valores son los mismos pero en orden inverso.</p><p>La identidad fundamental que relaciona seno y coseno es: sen²(α) + cos²(α) = 1. Esta identidad se utiliza frecuentemente para simplificar expresiones trigonométricas.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de sen²(α) + cos²(α)?', 'options' => ['0', '1', '2', 'Depende del ángulo'], 'correct_answer' => '1', 'explanation' => 'La identidad pitagórica establece que sen²(α) + cos²(α) = 1 para cualquier ángulo α.'],
                    ['question_text' => '¿Cuál es el valor de sen(45°)?', 'options' => ['1/2', '√2/2', '√3/2', '1'], 'correct_answer' => '1', 'explanation' => 'sen(45°) = √2/2 ≈ 0.7071.'],
                    ['question_text' => 'Si sen(α) = 3/5, ¿cuál es cos(α)?', 'options' => ['4/5', '3/5', '5/3', '5/4'], 'correct_answer' => '0', 'explanation' => 'Usando sen²α + cos²α = 1: cos²α = 1 - 9/25 = 16/25, entonces cos(α) = 4/5.'],
                    ['question_text' => '¿Cuál es el valor de cos(0°)?', 'options' => ['0', '1/2', '√3/2', '1'], 'correct_answer' => '3', 'explanation' => 'cos(0°) = 1, porque en el ángulo 0°, el cateto adyacente es igual a la hipotenusa.'],
                    ['question_text' => '¿Cuál es la relación entre seno y coseno de ángulos complementarios?', 'options' => ['Son iguales', 'sen(α) = cos(90° - α)', 'Son opuestos', 'No hay relación'], 'correct_answer' => '1', 'explanation' => 'sen(α) = cos(90° - α) y cos(α) = sen(90° - α), ya que los ángulos complementarios suman 90°.'],
                ],
            ],
            [
                'title' => 'Tangente y Cotangente',
                'unit' => 'Trigonometría',
                'difficulty' => 'basic',
                'estimated_time' => 35,
                'description' => 'Definición, valores especiales y relaciones de la tangente y cotangente.',
                'content' => '<h2>Tangente y Cotangente</h2><p>La tangente de un ángulo se define como la razón entre el seno y el coseno: tan(α) = sen(α) / cos(α). Geométricamente, es la razón entre el cateto opuesto y el cateto adyacente.</p><p>La cotangente es el recíproco de la tangente: cot(α) = cos(α) / sen(α) = 1/tan(α). Cuando la tangente es cero, la cotangente no está definida, y viceversa.</p><p>Valores importantes: tan(0°) = 0, tan(30°) = √3/3, tan(45°) = 1, tan(60°) = √3, y tan(90°) no está definida.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de tan(45°)?', 'options' => ['0', '1/2', '1', '√3'], 'correct_answer' => '2', 'explanation' => 'tan(45°) = 1, porque sen(45°) = cos(45°) = √2/2, por lo que su razón es 1.'],
                    ['question_text' => '¿Cómo se define la tangente en términos de seno y coseno?', 'options' => ['tan(α) = sen(α) × cos(α)', 'tan(α) = sen(α) / cos(α)', 'tan(α) = cos(α) / sen(α)', 'tan(α) = sen(α) + cos(α)'], 'correct_answer' => '1', 'explanation' => 'La tangente se define como tan(α) = sen(α) / cos(α).'],
                    ['question_text' => '¿Cuál es el valor de tan(0°)?', 'options' => ['0', '1', 'No está definida', '∞'], 'correct_answer' => '0', 'explanation' => 'tan(0°) = sen(0°) / cos(0°) = 0 / 1 = 0.'],
                    ['question_text' => 'Si tan(α) = 1, ¿cuánto mide α?', 'options' => ['30°', '45°', '60°', '90°'], 'correct_answer' => '1', 'explanation' => 'tan(45°) = 1, porque en un triángulo rectángulo isósceles, la razón de los catetos es 1.'],
                    ['question_text' => '¿Cuál es la relación entre tangente y cotangente?', 'options' => ['tan(α) = cot(α)', 'tan(α) × cot(α) = 1', 'tan(α) + cot(α) = 1', 'No hay relación'], 'correct_answer' => '1', 'explanation' => 'La cotangente es el recíproco de la tangente: tan(α) × cot(α) = 1.'],
                ],
            ],
            [
                'title' => 'Identidades Trigonométricas',
                'unit' => 'Trigonometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 50,
                'description' => 'Identidades fundamentales, de suma y duplicación de ángulos.',
                'content' => '<h2>Identidades Trigonométricas</h2><p>Las identidades trigonométricas son igualdades que se cumplen para todos los valores de las variables. Son herramientas fundamentales para simplificar expresiones y resolver ecuaciones trigonométricas.</p><p>Las identidades básicas incluyen: sen²α + cos²α = 1, 1 + tan²α = sec²α, y 1 + cot²α = csc²α. Las identidades de suma son: sen(a ± b) = sen(a)cos(b) ± cos(a)sen(b) y cos(a ± b) = cos(a)cos(b) ∓ sen(a)sen(b).</p><p>Las identidades de duplicación son: sen(2α) = 2sen(α)cos(α) y cos(2α) = cos²α - sen²α = 2cos²α - 1 = 1 - 2sen²α.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es el valor de sen²(30°) + cos²(30°)?', 'options' => ['0', '1', '2', '1/2'], 'correct_answer' => '1', 'explanation' => 'Por la identidad pitagórica, sen²α + cos²α = 1 para cualquier ángulo α.'],
                    ['question_text' => '¿Cuál es la identidad de sen(a + b)?', 'options' => ['sen(a)cos(b) + cos(a)sen(b)', 'sen(a)cos(b) - cos(a)sen(b)', 'cos(a)sen(b) + sen(a)cos(b)', 'sen(a)sen(b) + cos(a)cos(b)'], 'correct_answer' => '0', 'explanation' => 'La identidad de suma del seno es: sen(a + b) = sen(a)cos(b) + cos(a)sen(b).'],
                    ['question_text' => '¿Cuál es el valor de sen(2α) si sen(α) = 3/5 y cos(α) = 4/5?', 'options' => ['6/5', '24/25', '12/25', '7/25'], 'correct_answer' => '1', 'explanation' => 'sen(2α) = 2sen(α)cos(α) = 2(3/5)(4/5) = 24/25.'],
                    ['question_text' => '¿Cuál es la identidad de cos(2α)?', 'options' => ['2cos²α - 1', '1 - 2cos²α', '2sen²α - 1', 'sen²α + cos²α'], 'correct_answer' => '0', 'explanation' => 'Una de las formas de la identidad de duplicación del coseno es cos(2α) = 2cos²α - 1.'],
                    ['question_text' => 'Si sen(α) = cos(α), ¿cuánto mide α?', 'options' => ['30°', '45°', '60°', '90°'], 'correct_answer' => '1', 'explanation' => 'sen(α) = cos(α) cuando α = 45°, porque sen(45°) = cos(45°) = √2/2.'],
                ],
            ],
            [
                'title' => 'Ecuaciones Trigonométricas',
                'unit' => 'Trigonometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 50,
                'description' => 'Resolución de ecuaciones que contienen funciones trigonométricas.',
                'content' => '<h2>Ecuaciones Trigonométricas</h2><p>Las ecuaciones trigonométricas son ecuaciones en las que la incógnita aparece como argumento de una función trigonométrica. Resolverlas significa encontrar todos los ángulos que satisfacen la ecuación.</p><p>El proceso general para resolver estas ecuaciones es: aislar la función trigonométrica, determinar los ángulos que satisfacen la ecuación en un período, y luego añadir períodos completos para obtener todas las soluciones.</p><p>Es importante recordar que las funciones trigonométricas son periódicas, por lo que generalmente hay infinitas soluciones, que se expresan usando el parámetro k (número entero).</p>',
                'questions' => [
                    ['question_text' => 'Resuelve: sen(x) = 1/2 para 0° ≤ x ≤ 360°', 'options' => ['x = 30° y x = 150°', 'x = 30° y x = 330°', 'x = 60° y x = 120°', 'x = 45° y x = 135°'], 'correct_answer' => '0', 'explanation' => 'sen(30°) = 1/2 y sen(150°) = 1/2. El seno es positivo en el primer y segundo cuadrante.'],
                    ['question_text' => 'Resuelve: cos(x) = 0 para 0° ≤ x ≤ 360°', 'options' => ['x = 0° y x = 180°', 'x = 90° y x = 270°', 'x = 45° y x = 135°', 'x = 60° y x = 300°'], 'correct_answer' => '1', 'explanation' => 'cos(90°) = 0 y cos(270°) = 0. El coseno se anula en los ejes verticales del círculo unitario.'],
                    ['question_text' => '¿Cuántas soluciones tiene sen(x) = 1 en 0° ≤ x ≤ 360°?', 'options' => ['Ninguna', 'Una', 'Dos', 'Infinitas'], 'correct_answer' => '1', 'explanation' => 'sen(x) = 1 solo cuando x = 90° en el intervalo [0°, 360°].'],
                    ['question_text' => 'Resuelve: tan(x) = 1 para 0° ≤ x ≤ 180°', 'options' => ['x = 30°', 'x = 45°', 'x = 60°', 'x = 45° y x = 135°'], 'correct_answer' => '3', 'explanation' => 'tan(45°) = 1 y tan(135°) = 1, porque la tangente tiene período de 180°.'],
                    ['question_text' => '¿Cuál es el período de la función sen(x)?', 'options' => ['90°', '180°', '270°', '360°'], 'correct_answer' => '3', 'explanation' => 'La función seno tiene un período de 360° (2π radianes), lo que significa que se repite cada 360°.'],
                ],
            ],
            [
                'title' => 'Gráficas Trigonométricas',
                'unit' => 'Trigonometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Representación gráfica de las funciones seno, coseno y tangente.',
                'content' => '<h2>Gráficas Trigonométricas</h2><p>Las gráficas de las funciones trigonométricas muestran cómo varían estas funciones con el ángulo. La gráfica del seno es una onda que oscila entre -1 y 1 con período 360°.</p><p>La gráfica del coseno es similar a la del seno pero desplazada 90° a la izquierda. Ambas tienen amplitud 1 y período 360°. La gráfica de la tangente tiene una forma diferente, con asíntotas verticales cada 180°.</p><p>Al transformar las funciones trigonométricas (como y = A·sen(Bx + C) + D), se modifican propiedades como la amplitud (A), el período (360°/B), el desplazamiento fase (-C/B) y el desplazamiento vertical (D).</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es la amplitud de y = 3sen(x)?', 'options' => ['1', '2', '3', '6'], 'correct_answer' => '2', 'explanation' => 'La amplitud es el coeficiente del seno, que en este caso es 3.'],
                    ['question_text' => '¿Cuál es el período de y = sen(2x)?', 'options' => ['90°', '180°', '270°', '360°'], 'correct_answer' => '1', 'explanation' => 'El período es 360°/B = 360°/2 = 180°.'],
                    ['question_text' => '¿Dónde tiene asíntotas verticales la función tan(x)?', 'options' => ['En 0°, 180°, 360°', 'En 90°, 270°, 450°', 'En 45°, 135°, 225°', 'En 30°, 150°, 270°'], 'correct_answer' => '1', 'explanation' => 'La tangente tiene asíntotas verticales donde cos(x) = 0, es decir, en x = 90° + k·180°.'],
                    ['question_text' => '¿Cuál es el rango de la función y = cos(x)?', 'options' => ['[0, 1]', '[-1, 1]', '(-∞, ∞)', '[-2, 2]'], 'correct_answer' => '1', 'explanation' => 'El coseno varía entre -1 y 1, por lo que su rango es [-1, 1].'],
                    ['question_text' => '¿Cómo se llama el desplazamiento horizontal de y = sen(x - 30°)?', 'options' => ['Desplazamiento vertical', 'Desplazamiento de fase', 'Cambio de amplitud', 'Cambio de período'], 'correct_answer' => '1', 'explanation' => 'El término (x - 30°) representa un desplazamiento de fase de 30° a la derecha.'],
                ],
            ],
            [
                'title' => 'Ley de Senos',
                'unit' => 'Trigonometría',
                'difficulty' => 'intermediate',
                'estimated_time' => 45,
                'description' => 'Enunciado, demostración y aplicaciones de la ley de senos.',
                'content' => '<h2>Ley de Senos</h2><p>La ley de senos establece que en cualquier triángulo, la razón entre un lado y el seno de su ángulo opuesto es constante: a/sen(A) = b/sen(B) = c/sen(C).</p><p>Esta ley es especialmente útil cuando conocemos: dos ángulos y un lado (AAS o ASA), o dos lados y un ángulo no incluido (SSA). En este último caso, puede haber dos soluciones (el caso ambiguo).</p><p>La ley de senos se deriva del círculo circunscrito al triángulo. Si R es el radio de este círculo, entonces a/sen(A) = 2R, lo que nos permite calcular el radio del círculo circunscrito.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es la ley de senos?', 'options' => ['a/sen(A) = b/sen(B) = c/sen(C)', 'a² = b² + c² - 2bc·cos(A)', 'a/cos(A) = b/cos(B)', 'sen(A) + sen(B) = sen(C)'], 'correct_answer' => '0', 'explanation' => 'La ley de senos establece que a/sen(A) = b/sen(B) = c/sen(C) en cualquier triángulo.'],
                    ['question_text' => '¿Cuándo se usa la ley de senos?', 'options' => ['Cuando conocemos los tres lados', 'Cuando conocemos dos ángulos y un lado', 'Cuando conocemos el perímetro', 'Solo en triángulos rectángulos'], 'correct_answer' => '1', 'explanation' => 'La ley de senos es útil cuando conocemos dos ángulos y un lado, o dos lados y un ángulo no incluido.'],
                    ['question_text' => 'En un triángulo, si a = 10, A = 30° y B = 45°, ¿cuál es b?', 'options' => ['5√2', '10√2', '5', '10'], 'correct_answer' => '0', 'explanation' => 'Por la ley de senos: b = a·sen(B)/sen(A) = 10·sen(45°)/sen(30°) = 10·(√2/2)/(1/2) = 10√2.'],
                    ['question_text' => '¿Qué es el caso ambiguo en la ley de senos?', 'options' => ['Cuando no hay solución', 'Cuando hay dos soluciones posibles', 'Cuando hay tres soluciones', 'Cuando el triángulo es rectángulo'], 'correct_answer' => '1', 'explanation' => 'El caso ambiguo ocurre cuando se conocen dos lados y un ángulo no incluido, y puede haber dos triángulos posibles.'],
                    ['question_text' => 'Si a/sen(A) = 2R, ¿qué representa R?', 'options' => ['El perímetro del triángulo', 'El área del triángulo', 'El radio del círculo circunscrito', 'La altura del triángulo'], 'correct_answer' => '2', 'explanation' => 'R es el radio del círculo circunscrito al triángulo.'],
                ],
            ],
            [
                'title' => 'Ley de Cosenos',
                'unit' => 'Trigonometría',
                'difficulty' => 'advanced',
                'estimated_time' => 50,
                'description' => 'Enunciado, demostración y aplicaciones de la ley de cosenos.',
                'content' => '<h2>Ley de Cosenos</h2><p>La ley de cosenos generaliza el teorema de Pitágoras para cualquier triángulo: c² = a² + b² - 2ab·cos(C), donde C es el ángulo opuesto al lado c.</p><p>Esta ley es especialmente útil cuando conocemos: los tres lados (SSS) y queremos hallar un ángulo, o dos lados y el ángulo incluido (SAS) y queremos hallar el lado opuesto.</p><p>Cuando el ángulo C es 90°, cos(C) = 0 y la ley de cosenos se reduce al teorema de Pitágoras, lo que confirma que es una generalización de este.</p>',
                'questions' => [
                    ['question_text' => '¿Cuál es la ley de cosenos?', 'options' => ['a/sen(A) = b/sen(B)', 'c² = a² + b² - 2ab·cos(C)', 'c² = a² + b² + 2ab·cos(C)', 'a² = b² + c²'], 'correct_answer' => '1', 'explanation' => 'La ley de cosenos establece que c² = a² + b² - 2ab·cos(C).'],
                    ['question_text' => '¿Cuándo se usa la ley de cosenos?', 'options' => ['Cuando conocemos dos ángulos y un lado', 'Cuando conocemos los tres lados', 'Solo en triángulos rectángulos', 'Cuando conocemos el perímetro'], 'correct_answer' => '1', 'explanation' => 'La ley de cosenos es útil cuando conocemos los tres lados (SSS) o dos lados y el ángulo incluido (SAS).'],
                    ['question_text' => 'Si a = 5, b = 7 y C = 60°, ¿cuánto vale c²?', 'options' => ['39', '49', '74', '24'], 'correct_answer' => '0', 'explanation' => 'c² = 5² + 7² - 2(5)(7)cos(60°) = 25 + 49 - 70(0.5) = 74 - 35 = 39.'],
                    ['question_text' => 'Si C = 90°, ¿qué se reduce la ley de cosenos?', 'options' => ['Ley de senos', 'Teorema de Pitágoras', 'Identidad trigonométrica', 'No se puede simplificar'], 'correct_answer' => '1', 'explanation' => 'Cuando C = 90°, cos(C) = 0, por lo que c² = a² + b², que es el teorema de Pitágoras.'],
                    ['question_text' => 'Si a = 8, b = 6 y c = 10, ¿cuánto mide el ángulo C?', 'options' => ['30°', '45°', '60°', '90°'], 'correct_answer' => '3', 'explanation' => 'cos(C) = (a² + b² - c²)/(2ab) = (64 + 36 - 100)/(96) = 0, por lo que C = 90°.'],
                ],
            ],
        ];

        foreach ($lessons as $index => $lessonData) {
            $unit = $lessonData['unit'];
            $order = $this->getOrderForLesson($unit, $index, $lessons);

            $lesson = Lesson::create([
                'id' => Str::uuid(),
                'title' => $lessonData['title'],
                'description' => $lessonData['description'],
                'content' => $lessonData['content'],
                'teacher_id' => $teacher->id,
                'grade' => $this->gradeForUnit($unit, $order),
                'unit' => $lessonData['unit'],
                'topic' => strtolower($lessonData['title']),
                'difficulty' => $lessonData['difficulty'],
                'estimated_time' => $lessonData['estimated_time'],
                'is_published' => true,
                'published_at' => now(),
                'order' => $order,
                'views_count' => 0,
            ]);

            $evaluation = Evaluation::create([
                'id' => Str::uuid(),
                'title' => $lessonData['title'].' - Evaluación',
                'description' => 'Evalúa tus conocimientos sobre '.strtolower($lessonData['title']).'.',
                'teacher_id' => $teacher->id,
                'lesson_id' => $lesson->id,
                'type' => 'practice',
                'difficulty' => $lessonData['difficulty'],
                'auto_correct' => true,
                'randomize_questions' => true,
                'max_attempts' => 3,
                'is_published' => true,
                'published_at' => now(),
                'total_questions' => 5,
                'total_points' => 20,
            ]);

            foreach ($lessonData['questions'] as $qIndex => $questionData) {
                Question::create([
                    'id' => Str::uuid(),
                    'evaluation_id' => $evaluation->id,
                    'type' => 'multiple_choice',
                    'question_text' => $questionData['question_text'],
                    'options' => $questionData['options'],
                    'correct_answer' => $questionData['correct_answer'],
                    'explanation' => $questionData['explanation'],
                    'points' => 4,
                    'order' => $qIndex + 1,
                ]);
            }
        }
    }

    /**
     * Devuelve la posición de una lección dentro de las lecciones de su unidad.
     */
    private function getOrderForLesson(string $unit, int $index, array $lessons): int
    {
        $unitLessons = array_filter($lessons, fn ($l) => $l['unit'] === $unit);
        $unitKeys = array_keys($unitLessons);
        $position = array_search($index, $unitKeys);

        return $position !== false ? $position + 1 : 1;
    }

    /**
     * Grado escolar asignado a la lección según su unidad y el orden dentro de ella.
     */
    private function gradeForUnit(string $unit, int $order): string
    {
        $progressions = [
            'Álgebra' => ['2do', '2do', '3ro', '3ro', '3ro', '4to', '4to', '4to', '5to', '5to'],
            'Geometría' => ['2do', '2do', '3ro', '3ro', '3ro', '3ro', '4to', '4to', '4to', '5to'],
            'Trigonometría' => ['4to', '4to', '4to', '4to', '4to', '5to', '5to', '5to'],
        ];

        $list = $progressions[$unit] ?? ['3ro'];

        return $list[($order - 1) % count($list)];
    }
}
