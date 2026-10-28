<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Removes duplicate profile data after runtime reads use their canonical sources. */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateAcademicPeriods();
        $this->assertInstitutionValuesAreCanonical();
        $this->assertGamificationDataCanBeConsolidated();

        if (Schema::hasTable('student_profiles')) {
            $this->migrateLegacyBadges();

            $columns = array_values(array_filter([
                'total_lessons_completed',
                'average_score',
                'total_time_spent',
                'badges',
            ], fn (string $column) => Schema::hasColumn('student_profiles', $column)));

            if ($columns !== []) {
                Schema::table('student_profiles', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasColumn('teacher_profiles', 'students_count')) {
            Schema::table('teacher_profiles', function (Blueprint $table) {
                $table->dropColumn('students_count');
            });
        }

        if (Schema::hasColumn('users', 'institution')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('institution');
            });
        }

        if (Schema::hasTable('academic_periods') && ! Schema::hasIndex('academic_periods', 'academic_periods_name_unique')) {
            Schema::table('academic_periods', function (Blueprint $table) {
                $table->unique('name', 'academic_periods_name_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'institution')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('institution')->nullable();
            });

            $institutionName = DB::table('institution_configs')->value('institution_name');
            if ($institutionName !== null) {
                DB::table('users')->whereNull('institution')->update(['institution' => $institutionName]);
            }
        }

        if (Schema::hasTable('student_profiles')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->integer('total_lessons_completed')->default(0);
                $table->float('average_score')->default(0);
                $table->integer('total_time_spent')->default(0);
                $table->json('badges')->nullable();
            });

            DB::table('student_profiles')->orderBy('id')->chunkById(250, function ($profiles) {
                foreach ($profiles as $profile) {
                    $userId = $profile->user_id;
                    DB::table('student_profiles')->where('id', $profile->id)->update([
                        'total_lessons_completed' => DB::table('lesson_progress')
                            ->where('user_id', $userId)
                            ->where('status', 'completed')
                            ->count(),
                        'average_score' => DB::table('evaluation_results')
                            ->where('user_id', $userId)
                            ->where('status', 'completed')
                            ->avg('score') ?? 0,
                        'total_time_spent' => DB::table('lesson_progress')
                            ->where('user_id', $userId)
                            ->sum('time_spent'),
                        'badges' => json_encode(
                            DB::table('user_achievements')
                                ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
                                ->where('user_achievements.user_id', $userId)
                                ->pluck('achievements.slug')
                                ->all(),
                            JSON_THROW_ON_ERROR
                        ),
                    ]);
                }
            });
        }

        if (Schema::hasTable('teacher_profiles') && ! Schema::hasColumn('teacher_profiles', 'students_count')) {
            Schema::table('teacher_profiles', function (Blueprint $table) {
                $table->integer('students_count')->default(0);
            });
        }

        if (Schema::hasIndex('academic_periods', 'academic_periods_name_unique')) {
            Schema::table('academic_periods', function (Blueprint $table) {
                $table->dropUnique('academic_periods_name_unique');
            });
        }
    }

    private function assertNoDuplicateAcademicPeriods(): void
    {
        if (! Schema::hasTable('academic_periods')) {
            return;
        }

        $hasDuplicates = DB::table('academic_periods')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Duplicate academic period names exist; consolidate them before optimizing.');
        }
    }

    private function assertInstitutionValuesAreCanonical(): void
    {
        if (! Schema::hasColumn('users', 'institution')) {
            return;
        }

        $institutionName = DB::table('institution_configs')->value('institution_name');
        $hasUserInstitutions = DB::table('users')->whereNotNull('institution')->exists();
        $hasMismatch = DB::table('users')
            ->whereNotNull('institution')
            ->when($institutionName !== null, fn ($query) => $query->where('institution', '<>', $institutionName))
            ->exists();

        if (($hasUserInstitutions && $institutionName === null) || $hasMismatch) {
            throw new RuntimeException('User institution values do not match the canonical institution config.');
        }
    }

    private function assertGamificationDataCanBeConsolidated(): void
    {
        if (Schema::hasTable('student_profiles')
            && Schema::hasColumn('student_profiles', 'xp')
            && Schema::hasColumn('student_profiles', 'total_xp')
            && DB::table('student_profiles')->whereColumn('xp', '<>', 'total_xp')->exists()) {
            throw new RuntimeException('Student xp and total_xp values differ; reconcile them before removing xp.');
        }

        if (! Schema::hasColumn('student_profiles', 'badges') || ! Schema::hasTable('user_achievements')) {
            return;
        }

        $knownSlugs = DB::table('achievements')->pluck('id', 'slug')->all();
        foreach (DB::table('student_profiles')->whereNotNull('badges')->get(['user_id', 'badges']) as $profile) {
            $badges = json_decode((string) $profile->badges, true);
            if (! is_array($badges)) {
                throw new RuntimeException('Student profile badges contain invalid JSON.');
            }

            foreach ($badges as $badge) {
                if (! is_string($badge) || ! isset($knownSlugs[$badge])) {
                    throw new RuntimeException('A legacy student badge has no matching achievement.');
                }
            }
        }
    }

    private function migrateLegacyBadges(): void
    {
        if (! Schema::hasColumn('student_profiles', 'badges') || ! Schema::hasTable('user_achievements')) {
            return;
        }

        $achievementIds = DB::table('achievements')->pluck('id', 'slug')->all();
        DB::table('student_profiles')->whereNotNull('badges')->orderBy('id')->chunkById(250, function ($profiles) use ($achievementIds) {
            foreach ($profiles as $profile) {
                foreach (json_decode((string) $profile->badges, true) ?: [] as $slug) {
                    DB::table('user_achievements')->insertOrIgnore([
                        'user_id' => $profile->user_id,
                        'achievement_id' => $achievementIds[$slug],
                        'unlocked_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
};
