<?php

namespace App\Providers;

use App\Models\Evaluation;
use App\Models\Lesson;
use App\Policies\EvaluationPolicy;
use App\Policies\LessonPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/** Registra las políticas de autorización de lecciones y evaluaciones. */
class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Lesson::class => LessonPolicy::class,
        Evaluation::class => EvaluationPolicy::class,
    ];

    /** Registra en Laravel las políticas declaradas por el proveedor. */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
