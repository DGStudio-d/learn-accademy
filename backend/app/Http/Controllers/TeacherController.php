<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeacherController extends Controller
{
    // POST /api/teachers/{teacher}/image (teacher/admin)
    public function uploadImage(Request $request, User $teacher): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Allow admin or the teacher themselves
        $isAdmin = ($user->role === 'admin' || $user->hasRole('admin'));
        $isSelfTeacher = ($user->id === $teacher->id) && ($user->role === 'teacher' || $user->hasRole('teacher'));
        if (!($isAdmin || $isSelfTeacher)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'image' => ['required','image','max:4096'],
        ]);

        $path = $request->file('image')->store('teachers', ['disk' => config('filesystems.default')]);
        $teacher->image_path = $path;
        $teacher->save();

        return response()->json(['data' => ['image_path' => $path]]);
    }

    // POST /api/teachers/{teacher}/languages (admin assigns teacher to language)
    public function assignLanguages(Request $request, User $teacher): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $isAdmin = ($user->role === 'admin' || $user->hasRole('admin'));
        if (!$isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'language_ids' => ['required','array'],
            'language_ids.*' => ['integer','exists:languages,id'],
        ]);

        $teacher->languages()->sync($payload['language_ids']);

        return response()->json(['data' => $teacher->languages()->get(['languages.id','code','name'])]);
    }

    // GET /api/languages/{language}/teachers (guest if enabled)
    public function byLanguage(Language $language): JsonResponse
    {
        $guestAllowed = (bool) optional(Setting::where('key', 'allow_guest_teachers')->first())->value ?? false;
        if (!auth()->check() && !$guestAllowed) {
            return response()->json(['message' => 'Guest access to teachers is disabled'], 403);
        }

        $teachers = User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher');
            })
            ->whereHas('languages', function ($q) use ($language) {
                $q->where('languages.id', $language->id);
            })
            ->get(['id','name','image_path']);

        return response()->json(['data' => $teachers]);
    }
}
