<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class TranslationsController extends Controller
{
    // GET /api/translations/{locale}
    public function show(string $locale): JsonResponse
    {
        $path = resource_path("lang/{$locale}.json");
        if (!File::exists($path)) {
            return response()->json(['data' => new \stdClass()], 200);
        }

        $json = json_decode(File::get($path), true);
        if (!is_array($json)) {
            $json = [];
        }
        return response()->json(['data' => $json]);
    }
}
