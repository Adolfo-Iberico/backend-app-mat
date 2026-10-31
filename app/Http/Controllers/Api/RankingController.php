<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvaluationResult;
use App\Models\SubmittedWork;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Genera clasificaciones estudiantiles a partir de evaluaciones y trabajos calificados.
 */
class RankingController extends Controller
{
    /**
     * General course ranking by unit/area.
     */
    public function courseRanking(Request $request)
    {
        $request->validate([
            'unit' => 'required|string',
            'period' => 'nullable|in:week,month,quarter,year,all_time',
        ]);

        $unit = $request->unit;
        $periodFilter = $this->getPeriodFilter($request->period);

        $evalQuery = EvaluationResult::where('status', 'completed')
            ->whereHas('evaluation.lesson', fn ($q) => $q->where('unit', $unit));
        if ($periodFilter) {
            $evalQuery->whereBetween('created_at', $periodFilter);
        }

        $workQuery = SubmittedWork::where('status', 'graded')
            ->whereHas('lesson', fn ($q) => $q->where('unit', $unit));
        if ($periodFilter) {
            $workQuery->whereBetween('created_at', $periodFilter);
        }

        $evalScores = (clone $evalQuery)
            ->select('user_id', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->get();

        $workScores = (clone $workQuery)
            ->select('student_id as user_id', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('student_id')
            ->get();

        $merged = collect();
        foreach ($evalScores as $es) {
            $existing = $merged->firstWhere('user_id', $es->user_id);
            if ($existing) {
                $existing->avg_score = ($existing->avg_score + $es->avg_score) / 2;
                $existing->total += $es->total;
            } else {
                $merged->push((object) [
                    'user_id' => $es->user_id,
                    'avg_score' => $es->avg_score,
                    'total' => $es->total,
                ]);
            }
        }
        foreach ($workScores as $ws) {
            $existing = $merged->firstWhere('user_id', $ws->user_id);
            if ($existing) {
                $existing->avg_score = ($existing->avg_score + $ws->avg_score) / 2;
                $existing->total += $ws->total;
            } else {
                $merged->push((object) [
                    'user_id' => $ws->user_id,
                    'avg_score' => $ws->avg_score,
                    'total' => $ws->total,
                ]);
            }
        }

        $ranked = $merged->sortByDesc('avg_score')->values();

        // Precargar usuarios en una sola query (evita N+1 de User::find por estudiante)
        $userNames = User::whereIn('id', $ranked->pluck('user_id'))
            ->get(['id', 'full_name'])
            ->keyBy('id');

        $ranked = $ranked->map(function ($item, $index) use ($userNames) {
            return [
                'position' => $index + 1,
                'student_name' => $userNames->get($item->user_id)?->full_name ?? 'Unknown',
                'student_id' => $item->user_id,
                'average_score' => round($item->avg_score, 2),
                'total_works' => $item->total,
            ];
        });

        $myPosition = null;
        if (Auth::user()->isStudent()) {
            $myEntry = $ranked->firstWhere('student_id', Auth::id());
            $myPosition = $myEntry ? $myEntry['position'] : null;
        }

        return response()->json([
            'data' => $ranked,
            'my_position' => $myPosition,
        ]);
    }

    /**
     * Overall ranking across all areas.
     */
    public function overallRanking(Request $request)
    {
        $periodFilter = $this->getPeriodFilter($request->period);

        $evalQuery = EvaluationResult::where('status', 'completed');
        $workQuery = SubmittedWork::where('status', 'graded');

        if ($periodFilter) {
            $evalQuery->whereBetween('created_at', $periodFilter);
            $workQuery->whereBetween('created_at', $periodFilter);
        }

        $evalScores = (clone $evalQuery)
            ->select('user_id', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->get();

        $workScores = (clone $workQuery)
            ->select('student_id as user_id', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('student_id')
            ->get();

        $merged = collect();
        foreach ($evalScores as $es) {
            $existing = $merged->firstWhere('user_id', $es->user_id);
            if ($existing) {
                $existing->avg_score = ($existing->avg_score + $es->avg_score) / 2;
                $existing->total += $es->total;
            } else {
                $merged->push((object) [
                    'user_id' => $es->user_id,
                    'avg_score' => $es->avg_score,
                    'total' => $es->total,
                ]);
            }
        }
        foreach ($workScores as $ws) {
            $existing = $merged->firstWhere('user_id', $ws->user_id);
            if ($existing) {
                $existing->avg_score = ($existing->avg_score + $ws->avg_score) / 2;
                $existing->total += $ws->total;
            } else {
                $merged->push((object) [
                    'user_id' => $ws->user_id,
                    'avg_score' => $ws->avg_score,
                    'total' => $ws->total,
                ]);
            }
        }

        $ranked = $merged->sortByDesc('avg_score')->values();

        // Precargar usuarios en una sola query (evita N+1 de User::find por estudiante)
        $userNames = User::whereIn('id', $ranked->pluck('user_id'))
            ->get(['id', 'full_name'])
            ->keyBy('id');

        $ranked = $ranked->map(function ($item, $index) use ($userNames) {
            return [
                'position' => $index + 1,
                'student_name' => $userNames->get($item->user_id)?->full_name ?? 'Unknown',
                'student_id' => $item->user_id,
                'average_score' => round($item->avg_score, 2),
                'total_works' => $item->total,
            ];
        });

        $myPosition = null;
        if (Auth::user()->isStudent()) {
            $myEntry = $ranked->firstWhere('student_id', Auth::id());
            $myPosition = $myEntry ? $myEntry['position'] : null;
        }

        return response()->json([
            'data' => $ranked,
            'my_position' => $myPosition,
        ]);
    }

    /**
     * Current student's position in rankings.
     */
    public function myPosition(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        $evalScores = EvaluationResult::where('user_id', $userId)
            ->where('status', 'completed')
            ->select(DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->first();

        $workScores = SubmittedWork::where('student_id', $userId)
            ->where('status', 'graded')
            ->select(DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->first();

        $scores = [];
        if ($evalScores && $evalScores->total > 0) {
            $scores[] = $evalScores->avg_score;
        }
        if ($workScores && $workScores->total > 0) {
            $scores[] = $workScores->avg_score;
        }

        $overallAverage = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
        $totalWorks = ($evalScores?->total ?? 0) + ($workScores?->total ?? 0);

        // Calculate how many students have a higher average
        $allStudents = DB::table('evaluation_results')
            ->where('status', 'completed')
            ->select('user_id', DB::raw('AVG(score) as avg_score'))
            ->groupBy('user_id')
            ->get()
            ->merge(
                DB::table('submitted_works')
                    ->where('status', 'graded')
                    ->select('student_id as user_id', DB::raw('AVG(score) as avg_score'))
                    ->groupBy('student_id')
                    ->get()
            );

        $studentAvgs = collect();
        foreach ($allStudents as $s) {
            $existing = $studentAvgs->firstWhere('user_id', $s->user_id);
            if ($existing) {
                $existing->avg_score = ($existing->avg_score + $s->avg_score) / 2;
            } else {
                $studentAvgs->push((object) [
                    'user_id' => $s->user_id,
                    'avg_score' => $s->avg_score,
                ]);
            }
        }

        $aboveMe = $studentAvgs->where('avg_score', '>', $overallAverage)->count() + 1;
        $totalStudents = $studentAvgs->count();

        return response()->json([
            'data' => [
                'student_id' => $userId,
                'student_name' => $user->full_name,
                'overall_average' => $overallAverage,
                'total_works' => $totalWorks,
                'position' => $aboveMe,
                'total_students' => $totalStudents,
            ],
        ]);
    }

    private function getPeriodFilter(?string $period): ?array
    {
        if (! $period || $period === 'all_time') {
            return null;
        }

        $now = now();

        return match ($period) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => null,
        };
    }
}
