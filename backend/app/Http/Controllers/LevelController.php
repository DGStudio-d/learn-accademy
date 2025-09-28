<?php

namespace App\Http\Controllers;

use App\Models\Level;
use App\Models\Language;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    // GET /api/levels?language_id=
    public function index(Request $request): JsonResponse
    {
        // Guest access control: reuse allow_guest_languages setting
        $guestAllowed = (bool) optional(Setting::where('key', 'allow_guest_languages')->first())->value ?? false;
        if (!auth()->check() && !$guestAllowed) {
            return response()->json(['message' => 'Guest access to levels is disabled'], 403);
        }

        $query = Level::query()->with('language:id,code,name');
        if ($request->filled('language_id')) {
            $query->where('language_id', $request->integer('language_id'));
        }

        $levels = $query->orderBy('order')->get(['id','language_id','name','order']);
        return response()->json([
            'data' => $levels,
        ]);
    }

    // GET /api/languages/{language}/levels
    public function byLanguage(Language $language): JsonResponse
    {
        $guestAllowed = (bool) optional(Setting::where('key', 'allow_guest_languages')->first())->value ?? false;
        if (!auth()->check() && !$guestAllowed) {
            return response()->json(['message' => 'Guest access to levels is disabled'], 403);
        }

        $levels = $language->levels()->orderBy('order')->get(['id','language_id','name','order']);
        return response()->json([
            'data' => $levels,
        ]);
    }
}
