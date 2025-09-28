<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    // GET /api/programs (student only)
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($user->role !== 'student' && !$user->hasRole('student')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $locale = $request->get('locale');
        $query = Program::query()
            ->select(['programs.id','language_id','level_id','title','description'])
            ->join('enrollments', 'enrollments.program_id', '=', 'programs.id')
            ->where('enrollments.user_id', $user->id)
            ->where('enrollments.granted', true)
            ->orderBy('programs.id');

        if ($locale) {
            $query->with(['translations' => function ($t) use ($locale) {
                $t->where('locale', $locale);
            }]);
        }

        $programs = $query->get();

        $data = $programs->map(function ($p) use ($locale) {
            $title = $p->title;
            $description = $p->description;
            if ($locale && $p->relationLoaded('translations') && $p->translations->first()) {
                $title = $p->translations->first()->title ?? $title;
                $description = $p->translations->first()->description ?? $description;
            }
            return [
                'id' => $p->id,
                'language_id' => $p->language_id,
                'level_id' => $p->level_id,
                'title' => $title,
                'description' => $description,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
