<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    // GET /api/languages
    public function index(Request $request): JsonResponse
    {
        $guestAllowed = (bool) optional(Setting::where('key', 'allow_guest_languages')->first())->value ?? false;
        if (!auth()->check() && !$guestAllowed) {
            return response()->json(['message' => 'Guest access to languages is disabled'], 403);
        }

        $query = Language::query();
        $locale = $request->get('locale');

        if ($request->has('active')) {
            $query->where('active', filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('with_levels')) {
            $query->with(['levels' => function ($q) use ($locale) {
                $q->orderBy('order');
                if ($locale) {
                    $q->with(['translations' => function ($t) use ($locale) {
                        $t->where('locale', $locale);
                    }]);
                }
            }]);
        }

        if ($locale) {
            $query->with(['translations' => function ($t) use ($locale) {
                $t->where('locale', $locale);
            }]);
        }

        $languages = $query->orderBy('name')->get(['id','code','name','active']);
        $data = $languages->map(function ($lang) use ($request, $locale) {
            $item = [
                'id' => $lang->id,
                'code' => $lang->code,
                'name' => $lang->name,
                'active' => (bool)$lang->active,
            ];
            if ($locale && $lang->relationLoaded('translations') && $lang->translations->first()) {
                $item['name'] = $lang->translations->first()->name ?? $item['name'];
            }
            if ($request->boolean('with_levels') && $lang->relationLoaded('levels')) {
                $item['levels'] = $lang->levels->map(function ($lvl) use ($locale) {
                    $name = $lvl->name;
                    if ($locale && $lvl->relationLoaded('translations') && $lvl->translations->first()) {
                        $name = $lvl->translations->first()->name ?? $name;
                    }
                    return [
                        'id' => $lvl->id,
                        'language_id' => $lvl->language_id,
                        'name' => $name,
                        'order' => $lvl->order,
                    ];
                })->values();
            }
            return $item;
        })->values();

        return response()->json(['data' => $data]);
    }

    // GET /api/languages/{language}
    public function show(Language $language, Request $request): JsonResponse
    {
        $guestAllowed = (bool) optional(Setting::where('key', 'allow_guest_languages')->first())->value ?? false;
        if (!auth()->check() && !$guestAllowed) {
            return response()->json(['message' => 'Guest access to languages is disabled'], 403);
        }

        $locale = $request->get('locale');
        if ($locale) {
            $language->load(['translations' => function ($t) use ($locale) {
                $t->where('locale', $locale);
            }]);
        }
        if ($request->boolean('with_levels')) {
            $language->load(['levels' => function ($q) use ($locale) {
                $q->orderBy('order');
                if ($locale) {
                    $q->with(['translations' => function ($t) use ($locale) {
                        $t->where('locale', $locale);
                    }]);
                }
            }]);
        }

        $name = $language->name;
        if ($locale && $language->relationLoaded('translations') && $language->translations->first()) {
            $name = $language->translations->first()->name ?? $name;
        }

        $response = [
            'id' => $language->id,
            'code' => $language->code,
            'name' => $name,
            'active' => (bool)$language->active,
        ];
        if ($request->boolean('with_levels') && $language->relationLoaded('levels')) {
            $response['levels'] = $language->levels->map(function ($lvl) use ($locale) {
                $name = $lvl->name;
                if ($locale && $lvl->relationLoaded('translations') && $lvl->translations->first()) {
                    $name = $lvl->translations->first()->name ?? $name;
                }
                return [
                    'id' => $lvl->id,
                    'language_id' => $lvl->language_id,
                    'name' => $name,
                    'order' => $lvl->order,
                ];
            })->values();
        }

        return response()->json(['data' => $response]);
    }
}
