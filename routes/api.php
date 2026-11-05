<?php

use App\Http\Controllers\Api\AcademicEventController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\GuestStudentController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StudentManagerController;
use App\Http\Controllers\Api\SubmittedWorkController;
use App\Http\Controllers\Api\SystemTranslationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Todas las rutas de esta sección quedan bajo el prefijo /api/v1.

    // ============================================================
    // RUTAS PÚBLICAS (No requieren autenticación)
    // ============================================================
    // Incluye acceso y recuperación de cuenta; las rutas sensibles declaran su propio throttle.

    Route::prefix('auth')->group(function () {

        // Autenticación tradicional (email + password)
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        // Google OAuth
        // Requiere middleware web para que Socialite pueda guardar el estado OAuth en sesión.
        Route::get('/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])
            ->middleware(['web', 'throttle:30,1']);
        Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])
            ->middleware(['web', 'throttle:30,1']);
        Route::post('/google/exchange-code', [GoogleAuthController::class, 'exchangeCode'])
            ->middleware('throttle:10,1');
        Route::post('/google/login', [GoogleAuthController::class, 'loginWithGoogleToken'])
            ->middleware('throttle:10,1');

        // Recuperación de contraseña
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:5,1');
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
            ->middleware('throttle:5,1');
    });

    // ============================================================
    // RUTAS PROTEGIDAS (Requieren autenticación con Sanctum)
    // ============================================================
    // Este grupo exige token Sanctum, cuenta activa y el limitador rate.limit.

    Route::middleware(['auth:sanctum', 'auth.active', 'rate.limit'])->group(function () {

        // ============================================================
        // AI CHAT: proxy autenticado; generar lecciones requiere rol docente o administrador.
        // ============================================================
        Route::post('/ai/chat', [AiController::class, 'chat'])
            ->middleware('throttle:20,1');
        Route::post('/ai/generate-lesson', [AiController::class, 'generateLesson'])
            ->middleware(['role:teacher', 'throttle:10,1']);

        // ============================================================
        // PERFIL DE USUARIO: operaciones de cuenta disponibles a cualquier usuario autenticado.
        // ============================================================

        Route::prefix('user')->group(function () {
            Route::get('/profile', [AuthController::class, 'profile'])
                ->middleware('cache.api');
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/change-password', [AuthController::class, 'changePassword']);
            Route::patch('/student-profile', [ProgressController::class, 'updateMyStudentProfile'])
                ->middleware('role:student');
            Route::post('/send-verification-email', [AuthController::class, 'sendVerificationEmail'])
                ->middleware('throttle:3,1');
            Route::post('/verify-email', [AuthController::class, 'verifyEmail'])
                ->middleware('throttle:5,1');
            Route::post('/connect-google', [AuthController::class, 'connectGoogle']);
            Route::post('/disconnect-google', [AuthController::class, 'disconnectGoogle']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-platform', [AuthController::class, 'logoutPlatform']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::get('/devices', [AuthController::class, 'devices'])
                ->middleware('cache.api');
            Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        });

        // ============================================================
        // DISPOSITIVOS: administración de tokens push del usuario autenticado.
        // ============================================================

        Route::prefix('devices')->group(function () {
            Route::post('/register', [DeviceController::class, 'register']);
            Route::post('/unregister', [DeviceController::class, 'unregister']);
            Route::get('/', [DeviceController::class, 'list'])
                ->middleware('cache.api');
        });

        // ============================================================
        // NOTIFICACIONES: consulta y gestión de avisos del usuario autenticado.
        // ============================================================

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])
                ->middleware('cache.api');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])
                ->middleware('cache.api');
            Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/read/delete', [NotificationController::class, 'deleteRead']);
            Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
        });

        // ============================================================
        // DASHBOARDS: acceso por rol según cada ruta; incluye vistas completas y ligeras para móvil.
        // ============================================================

        Route::prefix('dashboard')->group(function () {
            // Dashboard completo (web)
            Route::get('/student', [ProgressController::class, 'studentDashboard'])
                ->middleware(['role:student', 'cache.api']);
            // Dashboard ligero (mobile) — solo stats
            Route::get('/student/stats', [ProgressController::class, 'studentStats'])
                ->middleware(['role:student', 'cache.api']);
            Route::get('/teacher', [ProgressController::class, 'teacherDashboard'])
                ->middleware(['role:teacher', 'cache.api']);
            Route::get('/admin', [AdminController::class, 'dashboard'])
                ->middleware(['role:admin', 'cache.api']);
            // Dashboard institucional (director y coordinador)
            Route::get('/institution', [InstitutionController::class, 'dashboard'])
                ->middleware(['role:coordinador', 'cache.api']);
        });

        // ============================================================
        // PROGRESO: estadísticas personales disponibles bajo la autenticación general del grupo.
        // ============================================================

        Route::prefix('progress')->group(function () {
            Route::get('/my-record', [ProgressController::class, 'myLearningRecord'])
                ->middleware('role:student');
            Route::get('/my-stats', [ProgressController::class, 'getMyStats'])
                ->middleware('cache.api');
            Route::get('/badges', [ProgressController::class, 'getBadges'])
                ->middleware('cache.api');
            Route::get('/level', [ProgressController::class, 'studentLevel'])
                ->middleware('cache.api');
            // Reporte propio exportable (estudiante)
            Route::get('/my-report/pdf', [ProgressController::class, 'exportMyReportPdf'])
                ->middleware(['role:student', 'throttle:5,1']);
            Route::get('/my-report/excel', [ProgressController::class, 'exportMyReportExcel'])
                ->middleware(['role:student', 'throttle:5,1']);
        });

        // ============================================================
        // LECCIONES: lectura autenticada; escritura y gestión reservadas al rol docente.
        // ============================================================

        Route::prefix('lessons')->group(function () {
            Route::get('/', [LessonController::class, 'index'])
                ->middleware(['cache.api', 'throttle:30,1']);
            Route::get('/recommended', [LessonController::class, 'recommended'])
                ->middleware('cache.api');
            // /unit/{unit} BEFORE /{id} to avoid route collision
            Route::get('/unit/{unit}', [LessonController::class, 'getByUnit'])
                ->middleware('cache.api');
            Route::get('/{id}', [LessonController::class, 'show'])
                ->middleware('cache.api');
            // Contenido completo de lección (separado para mobile)
            Route::get('/{id}/content', [LessonController::class, 'content'])
                ->middleware('cache.api');
            Route::get('/{id}/resources', [LessonController::class, 'getResources'])
                ->middleware('cache.api');
            Route::get('/{id}/progress', [ProgressController::class, 'getLessonProgress'])
                ->middleware('cache.api');
            Route::post('/{id}/progress', [ProgressController::class, 'updateLessonProgress'])
                ->middleware(['role:student']);

            Route::middleware(['role:teacher'])->group(function () {
                Route::post('/', [LessonController::class, 'store']);
                Route::post('/resources/upload', [LessonController::class, 'uploadResource'])
                    ->middleware(['throttle:30,1']);
                Route::put('/{id}', [LessonController::class, 'update']);
                Route::delete('/{id}', [LessonController::class, 'destroy']);
                Route::post('/{id}/publish', [LessonController::class, 'publish']);
                Route::post('/{id}/unpublish', [LessonController::class, 'unpublish']);
                Route::post('/{id}/duplicate', [LessonController::class, 'duplicate']);
                Route::post('/{id}/resources', [LessonController::class, 'addResource']);
                Route::post('/{id}/resources/import', [LessonController::class, 'importResourcesFromStorage']);
                Route::delete('/{id}/resources/{resourceId}', [LessonController::class, 'removeResource']);
                Route::get('/{id}/stats', [LessonController::class, 'getStats'])
                    ->middleware('cache.api');
            });
        });

        // ============================================================
        // EVALUACIONES: lectura autenticada; envío para estudiantes y gestión para docentes.
        // ============================================================

        Route::prefix('evaluations')->group(function () {
            Route::get('/', [EvaluationController::class, 'index'])
                ->middleware(['cache.api', 'throttle:30,1']);
            Route::get('/adaptive', [EvaluationController::class, 'adaptive'])
                ->middleware('cache.api');
            Route::get('/{id}', [EvaluationController::class, 'show'])
                ->middleware('cache.api');
            Route::get('/{evaluationId}/questions', [EvaluationController::class, 'getQuestions'])
                ->middleware('cache.api');
            Route::post('/{evaluationId}/start', [EvaluationController::class, 'start'])
                ->middleware(['role:student', 'throttle:10,1']);
            Route::post('/{evaluationId}/attempts/{attemptId}/tab-switch', [EvaluationController::class, 'reportTabSwitch'])
                ->middleware(['role:student', 'throttle:30,1']);
            Route::post('/{evaluationId}/submit', [EvaluationController::class, 'submit'])
                ->middleware(['role:student']);
            Route::get('/{evaluationId}/results/{resultId}/pdf', [EvaluationController::class, 'exportStudentResultPdf'])
                ->middleware(['role:student', 'throttle:5,1']);
            Route::get('/{evaluationId}/results/{resultId}/excel', [EvaluationController::class, 'exportStudentResultExcel'])
                ->middleware(['role:student', 'throttle:5,1']);
            Route::get('/{evaluationId}/results', [EvaluationController::class, 'getResults'])
                ->middleware('cache.api');

            Route::middleware(['role:teacher'])->group(function () {
                Route::get('/{evaluationId}/result/{userId}', [EvaluationController::class, 'getStudentResult'])
                    ->middleware('cache.api');
                Route::get('/{id}/stats', [EvaluationController::class, 'getStats'])
                    ->middleware('cache.api');
            });

            Route::middleware(['role:teacher'])->group(function () {
                Route::post('/', [EvaluationController::class, 'store']);
                Route::put('/{id}', [EvaluationController::class, 'update']);
                Route::delete('/{id}', [EvaluationController::class, 'destroy']);
                Route::post('/{id}/publish', [EvaluationController::class, 'publish']);
                Route::post('/{id}/unpublish', [EvaluationController::class, 'unpublish']);
                Route::post('/{id}/duplicate', [EvaluationController::class, 'duplicate']);

                Route::post('/{evaluationId}/questions', [EvaluationController::class, 'addQuestion']);
                Route::put('/questions/{questionId}', [EvaluationController::class, 'updateQuestion']);
                Route::delete('/questions/{questionId}', [EvaluationController::class, 'deleteQuestion']);
            });
        });

        // ============================================================
        // EXÁMENES: pruebas independientes; acciones de creación/gestión requieren docente o admin.
        // ============================================================

        Route::prefix('exams')->group(function () {
            Route::get('/', [ExamController::class, 'index'])
                ->middleware(['cache.api', 'throttle:30,1']);
            // Historial de intentos del estudiante (antes de /{id} para evitar colisión)
            Route::get('/my-attempts', [ExamController::class, 'myAttempts'])
                ->middleware(['role:student', 'cache.api']);
            Route::post('/', [ExamController::class, 'store'])
                ->middleware(['role:teacher']);
            Route::get('/{id}', [ExamController::class, 'show'])
                ->middleware('cache.api');
            Route::put('/{id}', [ExamController::class, 'update'])
                ->middleware(['role:teacher']);
            Route::delete('/{id}', [ExamController::class, 'destroy'])
                ->middleware(['role:teacher']);
            Route::post('/{id}/activate', [ExamController::class, 'activate'])
                ->middleware(['role:teacher']);
            Route::post('/{id}/deactivate', [ExamController::class, 'deactivate'])
                ->middleware(['role:teacher']);
            Route::post('/{id}/start', [ExamController::class, 'startAttempt'])
                ->middleware(['role:student']);
            Route::post('/attempts/{attemptId}/submit', [ExamController::class, 'submitAttempt'])
                ->middleware(['role:student']);
            Route::post('/attempts/{attemptId}/cheat', [ExamController::class, 'reportCheating'])
                ->middleware(['role:student']);
            Route::get('/{id}/stats', [ExamController::class, 'getExamStats'])
                ->middleware(['role:teacher', 'cache.api']);
        });

        // ============================================================
        // TRABAJOS ENTREGADOS: acceso autenticado; entrega para estudiantes y calificación para docentes/admin.
        // ============================================================

        Route::get('/submitted-works', [SubmittedWorkController::class, 'index']);
        Route::post('/submitted-works', [SubmittedWorkController::class, 'store'])
            ->middleware(['role:student', 'throttle:20,1']);
        Route::get('/submitted-works/student/summary', [SubmittedWorkController::class, 'studentSummary']);
        // Estadísticas y export para el panel de administración (antes de /{id}).
        Route::get('/submitted-works/stats', [SubmittedWorkController::class, 'stats'])
            ->middleware(['role:admin,coordinador,director', 'throttle:30,1']);
        Route::get('/submitted-works/export', [SubmittedWorkController::class, 'export'])
            ->middleware(['role:admin,coordinador,director', 'throttle:10,1']);
        // Calificación por lote para el panel docente.
        Route::post('/submitted-works/batch-grade', [SubmittedWorkController::class, 'batchGrade'])
            ->middleware(['role:teacher,admin', 'throttle:20,1']);
        Route::get('/submitted-works/{id}', [SubmittedWorkController::class, 'show']);
        Route::post('/submitted-works/{id}/grade', [SubmittedWorkController::class, 'grade'])
            ->middleware('role:teacher');
        Route::post('/submitted-works/{id}/return', [SubmittedWorkController::class, 'returnWork'])
            ->middleware('role:teacher');
        Route::post('/submitted-works/auto-generate', [SubmittedWorkController::class, 'autoGenerateFromCompleted'])
            ->middleware(['role:teacher', 'throttle:10,1']);

        // ============================================================
        // RANKINGS: consulta de posiciones dentro del grupo autenticado.
        // ============================================================

        Route::get('/rankings/course', [RankingController::class, 'courseRanking']);
        Route::get('/rankings/overall', [RankingController::class, 'overallRanking']);
        Route::get('/rankings/my-position', [RankingController::class, 'myPosition']);

        // ============================================================
        // REPORTES: disponibles para docentes y administradores; la
        // jerarquía del middleware también incluye a coordinadores y directores.
        // ============================================================

        Route::prefix('reports')->middleware(['role:teacher,admin'])->group(function () {
            Route::get('/performance', [ReportController::class, 'performanceReport'])
                ->middleware('cache.api');
            Route::get('/filtered-performance', [ReportController::class, 'filteredPerformanceReport'])
                ->middleware('cache.api');
            Route::get('/student-detail/{studentId}', [ReportController::class, 'studentDetailReport'])
                ->middleware('cache.api');
            Route::get('/course-detail/{unit}', [ReportController::class, 'courseDetailReport'])
                ->middleware('cache.api');
            Route::get('/grades', [ReportController::class, 'gradesReport'])
                ->middleware('cache.api');
            Route::get('/student/{userId}', [ReportController::class, 'studentReport'])
                ->middleware('cache.api');
            Route::get('/participation', [ReportController::class, 'participationReport'])
                ->middleware('cache.api');
            Route::get('/export/pdf', [ReportController::class, 'exportPDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/excel', [ReportController::class, 'exportExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/performance/pdf', [ReportController::class, 'exportPerformancePDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/performance/excel', [ReportController::class, 'exportPerformanceExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/student/{studentId}/pdf', [ReportController::class, 'exportStudentReportPDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/student/{studentId}/excel', [ReportController::class, 'exportStudentReportExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/grades/pdf', [ReportController::class, 'exportGradesPDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/grades/excel', [ReportController::class, 'exportGradesExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/student/{studentId}/csv', [ReportController::class, 'exportStudentReportCSV'])
                ->middleware('throttle:5,1');
            Route::get('/export/grades/csv', [ReportController::class, 'exportGradesCSV'])
                ->middleware('throttle:5,1');
        });

        // ============================================================
        // ANUNCIOS: anuncios globales activos publicados por el admin.
        // ============================================================

        Route::prefix('announcements')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])
                ->middleware('cache.api');
        });

        // ============================================================
        // ADMINISTRACIÓN: todas las rutas /admin requieren rol admin, además de la autenticación general.
        // ============================================================

        Route::prefix('admin')->middleware(['role:admin'])->group(function () {

            Route::prefix('users')->group(function () {
                Route::get('/', [AdminController::class, 'getUsers'])
                    ->middleware('cache.api');
                Route::post('/', [AdminController::class, 'createUser']);
                Route::post('/import', [AdminController::class, 'importUsers'])
                    ->middleware(['throttle:5,1']);
                Route::get('/export', [AdminController::class, 'exportUsers']);
                Route::get('/{id}', [AdminController::class, 'getUser'])
                    ->middleware('cache.api');
                Route::put('/{id}', [AdminController::class, 'updateUser']);
                Route::delete('/{id}', [AdminController::class, 'deleteUser']);
                Route::post('/{id}/activate', [AdminController::class, 'activateUser']);
                Route::post('/{id}/deactivate', [AdminController::class, 'deactivateUser']);
            });

            Route::prefix('config')->group(function () {
                Route::get('/', [AdminController::class, 'getConfig'])
                    ->middleware('cache.api');
                Route::put('/', [AdminController::class, 'updateConfig']);
            });

            Route::prefix('periods')->group(function () {
                Route::get('/', [AdminController::class, 'getPeriods'])
                    ->middleware('cache.api');
                Route::post('/', [AdminController::class, 'createPeriod']);
                Route::put('/{id}', [AdminController::class, 'updatePeriod']);
                Route::delete('/{id}', [AdminController::class, 'deletePeriod']);
            });

            Route::prefix('backup')->group(function () {
                Route::post('/', [AdminController::class, 'createBackup'])
                    ->middleware(['throttle:3,60']);
                Route::get('/last', [AdminController::class, 'getLastBackup'])
                    ->middleware('cache.api');
                Route::get('/download/{filename}', [AdminController::class, 'downloadBackup']);
            });

            Route::prefix('translations')->group(function () {
                Route::get('/', [SystemTranslationController::class, 'index'])
                    ->middleware('cache.api');
                Route::post('/', [SystemTranslationController::class, 'store'])
                    ->middleware(['throttle:60,1']);
                Route::post('/bulk', [SystemTranslationController::class, 'bulkUpdate'])
                    ->middleware(['throttle:30,1']);
                Route::delete('/{id}', [SystemTranslationController::class, 'destroy']);
            });

            // Anuncios globales del sistema
            Route::prefix('announcements')->group(function () {
                Route::get('/', [AnnouncementController::class, 'adminIndex']);
                Route::post('/', [AnnouncementController::class, 'store']);
                Route::put('/{id}', [AnnouncementController::class, 'update']);
                Route::delete('/{id}', [AnnouncementController::class, 'destroy']);
            });

            Route::post('/achievements/sync', [GamificationController::class, 'sync']);
        });

        // ============================================================
        // GAMIFICACIÓN: prefijo con rol student; cada endpoint gestiona logros del estudiante.
        // ============================================================

        Route::prefix('gamification')->group(function () {
            // Resumen propio del estudiante
            Route::get('/summary', [GamificationController::class, 'summary'])
                ->middleware(['role:student', 'cache.api']);
            Route::post('/check', [GamificationController::class, 'check'])
                ->middleware(['role:student', 'throttle:20,1']);
            // Vista docente de la gamificación de un alumno
            Route::get('/student/{studentId}', [GamificationController::class, 'studentSummary'])
                ->middleware(['role:coordinador', 'cache.api']);
        });

        // ============================================================
        // CALENDARIO: prefijo limitado a docentes/admin para gestionar eventos académicos.
        // ============================================================

        Route::prefix('calendar')->middleware(['role:teacher'])->group(function () {
            Route::get('/', [AcademicEventController::class, 'index'])
                ->middleware('cache.api');
            Route::post('/', [AcademicEventController::class, 'store']);
            Route::put('/{id}', [AcademicEventController::class, 'update']);
            Route::delete('/{id}', [AcademicEventController::class, 'destroy']);
        });

        // ============================================================
        // PUSH WEB: consulta de configuración y gestión de suscripciones; throttle por operación.
        // ============================================================

        Route::prefix('push')->group(function () {
            Route::get('/config', [PushSubscriptionController::class, 'config'])
                ->middleware('cache.api');
            Route::post('/subscribe', [PushSubscriptionController::class, 'store'])
                ->middleware(['throttle:10,1']);
            Route::post('/unsubscribe', [PushSubscriptionController::class, 'destroy']);
            Route::post('/test', [PushSubscriptionController::class, 'test'])
                ->middleware('throttle:5,1');
        });

        // ============================================================
        // MENSAJERÍA: operaciones de conversación con límite de 30 solicitudes por minuto.
        // ============================================================

        Route::prefix('conversations')->middleware(['auth:sanctum', 'auth.active', 'rate.limit', 'throttle:30,1'])->group(function () {
            Route::get('/', [MessageController::class, 'index']);
            Route::post('/', [MessageController::class, 'store']);
            Route::get('/{id}', [MessageController::class, 'show']);
            Route::post('/{id}/reply', [MessageController::class, 'reply']);
        });

        // ============================================================
        // FORO: hilos y publicaciones con límite de 30 solicitudes por minuto.
        // ============================================================

        Route::prefix('forum')->middleware(['throttle:30,1'])->group(function () {
            Route::get('/', [ForumController::class, 'index']);
            // Crear hilos: cualquier usuario autenticado; los estudiantes deben
            // asociar la pregunta a una lección (validado en el controlador).
            Route::post('/', [ForumController::class, 'store']);
            Route::get('/{id}', [ForumController::class, 'show']);
            Route::post('/{id}/post', [ForumController::class, 'post']);
            // Moderación: editar y eliminar comentarios (autor o dueño del hilo)
            Route::put('/posts/{postId}', [ForumController::class, 'updatePost']);
            Route::delete('/posts/{postId}', [ForumController::class, 'deletePost']);
            // Cerrar hilo: solo el docente dueño
            Route::post('/{id}/close', [ForumController::class, 'close'])
                ->middleware('role:teacher');
        });

        // ============================================================
        // GESTOR DE ALUMNOS: vista institucional por grado/sección y carga
        // masiva (CSV/Excel). Acceso: docente, coordinador, director y admin.
        // ============================================================
        Route::prefix('students-manager')
            ->middleware(['role:teacher', 'throttle:60,1'])
            ->group(function () {
                Route::get('/summary', [StudentManagerController::class, 'summary']);
                Route::get('/', [StudentManagerController::class, 'index']);
                Route::get('/template', [StudentManagerController::class, 'template']);
                Route::get('/export', [StudentManagerController::class, 'export']);
                Route::post('/import', [StudentManagerController::class, 'import'])->middleware('throttle:10,1');
                Route::patch('/{id}', [StudentManagerController::class, 'update']);
            });

        // ============================================================
        // INSTITUCIÓN: panel para director y coordinador.
        // ============================================================

        Route::prefix('institution')->middleware(['role:coordinador'])->group(function () {
            Route::get('/overview', [InstitutionController::class, 'overview'])
                ->middleware('cache.api');
            Route::get('/teachers', [InstitutionController::class, 'teachers'])
                ->middleware('cache.api');
        });
    });

    // ============================================================

    // CONSULTA DE INVITADOS: búsqueda de estudiante y generación de captcha con límites por ruta.
    Route::post('/guest/student-lookup', [GuestStudentController::class, 'lookup'])->middleware('throttle:5,1');
    Route::post('/guest/student-lookup/export', [GuestStudentController::class, 'exportLookup'])->middleware('throttle:5,1');
    Route::get('/guest/captcha', [GuestStudentController::class, 'generateCaptcha'])->middleware('throttle:10,1');

    // ============================================================
    // RUTAS PÚBLICAS: configuración y traducciones consultables sin autenticación, con throttle.
    // ============================================================

    Route::get('/config', [AdminController::class, 'publicConfig'])
        ->middleware('throttle:30,1');

    Route::get('/translations/overrides', [SystemTranslationController::class, 'publicOverrides'])
        ->middleware('throttle:60,1');

    // ============================================================
    // HEALTH CHECK: confirma disponibilidad de la API y devuelve su versión y hora actual.
    // ============================================================

    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'message' => 'Aulamate API is running',
            'version' => 'v1',
            'timestamp' => now(),
        ]);
    });
});
