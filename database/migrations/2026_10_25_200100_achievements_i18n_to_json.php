<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — Internacionalización de logros: 6 columnas planas → 2 JSON.
 *
 *   name_es, name_en, name_qu             -> name        {"es": "...", "en": "...", "qu": "..."}
 *   description_es, description_en, description_qu -> description {"es": ..., "en": ..., "qu": ...}
 *
 * El modelo Achievement expone accessors name_es/name_en/... de modo que
 * todo el código existente (GamificationService, ExamController,
 * ProgressController) sigue funcionando sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('achievements', 'name_es')) {
            return; // Ya migrado.
        }

        Schema::table('achievements', function (Blueprint $table) {
            $table->json('name')->nullable()->after('slug');
            $table->json('description')->nullable()->after('name');
        });

        // Backfill portable (PHP, no SQL específico del driver).
        foreach (DB::table('achievements')->get() as $achievement) {
            DB::table('achievements')->where('id', $achievement->id)->update([
                'name' => $this->toJson($achievement->name_es, $achievement->name_en, $achievement->name_qu),
                'description' => $this->toJson($achievement->description_es, $achievement->description_en, $achievement->description_qu),
            ]);
        }

        // es/en son obligatorios (eran NOT NULL en el esquema original).
        Schema::table('achievements', function (Blueprint $table) {
            $table->json('name')->nullable(false)->change();
            $table->json('description')->nullable(false)->change();
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn(['name_es', 'name_en', 'name_qu', 'description_es', 'description_en', 'description_qu']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('achievements', 'name')) {
            return;
        }

        Schema::table('achievements', function (Blueprint $table) {
            $table->string('name_es')->default('');
            $table->string('name_en')->default('');
            $table->string('name_qu')->nullable();
            $table->string('description_es')->default('');
            $table->string('description_en')->default('');
            $table->string('description_qu')->nullable();
        });

        foreach (DB::table('achievements')->get() as $achievement) {
            $name = json_decode((string) $achievement->name, true) ?? [];
            $description = json_decode((string) $achievement->description, true) ?? [];

            DB::table('achievements')->where('id', $achievement->id)->update([
                'name_es' => $name['es'] ?? '',
                'name_en' => $name['en'] ?? '',
                'name_qu' => $name['qu'] ?? null,
                'description_es' => $description['es'] ?? '',
                'description_en' => $description['en'] ?? '',
                'description_qu' => $description['qu'] ?? null,
            ]);
        }

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });
    }

    /** Construye el JSON de traducciones omitiendo locales vacíos. */
    private function toJson(?string $es, ?string $en, ?string $qu): string
    {
        $translations = array_filter(
            ['es' => $es, 'en' => $en, 'qu' => $qu],
            fn ($value) => $value !== null && $value !== ''
        );

        return json_encode($translations, JSON_UNESCAPED_UNICODE);
    }
};
