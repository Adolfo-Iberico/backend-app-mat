<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Gestiona traducciones almacenadas y sirve sobrescrituras en caché para el frontend.
 */
class SystemTranslationController extends Controller
{
    /**
     * Listar traducciones con filtros por grupo/idioma/clave/búsqueda.
     */
    public function index(Request $request)
    {
        $query = SystemTranslation::query();

        if ($request->filled('group')) {
            $query->where('group', $request->input('group'));
        }
        if ($request->filled('locale')) {
            $query->where('locale', $request->input('locale'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }

        $translations = $query->orderBy('key')->paginate($request->input('per_page', 50));

        return response()->json([
            'translations' => $translations->items(),
            'total' => $translations->total(),
            'per_page' => $translations->perPage(),
            'current_page' => $translations->currentPage(),
        ]);
    }

    /**
     * Crear o actualizar (upsert) una traducción.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'locale' => 'required|in:es,en,qu',
            'value' => 'required|string',
            'group' => 'nullable|string|max:20',
        ]);

        $translation = SystemTranslation::updateOrCreate(
            [
                'key' => $validated['key'],
                'locale' => $validated['locale'],
            ],
            [
                'value' => $validated['value'],
                'group' => $validated['group'] ?? 'frontend',
            ]
        );

        $this->flushCache();

        return response()->json([
            'message' => __('translation_saved'),
            'translation' => $translation,
        ], 201);
    }

    /**
     * Actualizar en lote varias traducciones a la vez.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.key' => 'required|string|max:255',
            'items.*.locale' => 'required|in:es,en,qu',
            'items.*.value' => 'required|string',
            'group' => 'nullable|string|max:20',
        ]);

        $count = 0;
        foreach ($validated['items'] as $item) {
            SystemTranslation::updateOrCreate(
                ['key' => $item['key'], 'locale' => $item['locale']],
                [
                    'value' => $item['value'],
                    'group' => $validated['group'] ?? 'frontend',
                ]
            );
            $count++;
        }

        $this->flushCache();

        return response()->json([
            'message' => __('translations_saved'),
            'updated' => $count,
        ]);
    }

    /**
     * Eliminar una traducción.
     */
    public function destroy(Request $request, $id)
    {
        $deleted = SystemTranslation::where('id', $id)->delete();

        if (! $deleted) {
            return response()->json(['message' => __('translation_not_found')], 404);
        }

        $this->flushCache();

        return response()->json(['message' => __('translation_deleted')]);
    }

    /**
     * Endpoint público: overrides de traducciones para el frontend.
     */
    public function publicOverrides(Request $request)
    {
        $locale = $request->input('locale', 'es');

        return Cache::remember('translations.overrides.'.$locale, 3600, function () use ($locale) {
            $rows = SystemTranslation::where('group', 'frontend')
                ->where('locale', $locale)
                ->get(['key', 'value']);

            $map = [];
            foreach ($rows as $row) {
                $this->setNested($map, $row->key, $row->value);
            }

            return response()->json(['overrides' => $map]);
        });
    }

    /**
     * Convierte una clave con puntos (nav.dashboard) en un objeto anidado.
     */
    private function setNested(array &$target, string $key, string $value): void
    {
        $parts = explode('.', $key);
        $current = &$target;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (! isset($current[$part]) || ! is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
        unset($current);
    }

    private function flushCache(): void
    {
        foreach (SystemTranslation::LOCALES as $locale) {
            Cache::forget('translations.overrides.'.$locale);
        }
    }
}
