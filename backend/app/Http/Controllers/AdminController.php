<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Models\Language;
use App\Models\Level;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\LanguageTranslation;
use App\Models\LevelTranslation;
use App\Models\ProgramTranslation;
use App\Models\MeetingTranslation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private function ensureAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'admin' || (method_exists($user, 'hasRole') && $user->hasRole('admin')))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return null;
    }

    // POST /api/languages (admin) - create language and auto-generate levels and programs
    public function createLanguage(Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $data = $request->validate([
            'code' => ['required','string','max:10','unique:languages,code'],
            'name' => ['required','string','max:255'],
            // Optional custom levels structure
            'levels' => ['sometimes','array'],
            'levels.*.name' => ['required_with:levels','string','max:255'],
            'levels.*.order' => ['required_with:levels','integer','min:0'],
        ]);

        $language = Language::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'active' => true,
        ]);

        $levels = $data['levels'] ?? [
            ['name' => 'Beginner', 'order' => 1],
            ['name' => 'Intermediate', 'order' => 2],
            ['name' => 'Advanced', 'order' => 3],
        ];

        foreach ($levels as $lev) {
            $level = Level::create([
                'language_id' => $language->id,
                'name' => $lev['name'],
                'order' => $lev['order'],
            ]);
            // Auto-generate a default program for each level
            Program::create([
                'language_id' => $language->id,
                'level_id' => $level->id,
                'title' => $language->name.' '.$level->name.' Program',
                'description' => null,
            ]);
        }

        return response()->json(['data' => $language->only(['id','code','name','active'])], 201);
    }

    // PATCH /api/admin/settings
    public function updateSettings(Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $data = $request->validate([
            'allow_guest_languages' => ['sometimes','boolean'],
            'allow_guest_teachers' => ['sometimes','boolean'],
            'allow_guest_quizzes' => ['sometimes','boolean'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (bool)$value]);
        }

        return response()->json(['data' => $data]);
    }

    // GET /api/admin/programs/{program}/students
    public function programStudents(Program $program, Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $students = $program->enrollments()
            ->with(['user:id,name,email'])
            ->get()
            ->map(fn($e) => [
                'id' => $e->user->id,
                'name' => $e->user->name,
                'email' => $e->user->email,
                'assigned_at' => $e->assigned_at,
            ]);

        return response()->json(['data' => $students]);
    }

    // PATCH /api/admin/enrollments/{enrollment}
    public function updateEnrollment(Enrollment $enrollment, Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }

        $data = $request->validate([
            'granted' => ['required','boolean'],
        ]);

        $enrollment->update(['granted' => (bool)$data['granted']]);

        return response()->json([
            'data' => $enrollment->only(['id','user_id','program_id','granted']),
        ]);
    }

    // PUT /api/admin/languages/{language}/translations
    public function upsertLanguageTranslation(Language $language, Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }
        $data = $request->validate([
            'locale' => ['required','string','max:10'],
            'name' => ['required','string','max:255'],
        ]);
        $row = LanguageTranslation::updateOrCreate(
            ['language_id' => $language->id, 'locale' => $data['locale']],
            ['name' => $data['name']]
        );
        return response()->json(['data' => $row->only(['id','language_id','locale','name'])]);
    }

    // PUT /api/admin/levels/{level}/translations
    public function upsertLevelTranslation(Level $level, Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }
        $data = $request->validate([
            'locale' => ['required','string','max:10'],
            'name' => ['required','string','max:255'],
        ]);
        $row = LevelTranslation::updateOrCreate(
            ['level_id' => $level->id, 'locale' => $data['locale']],
            ['name' => $data['name']]
        );
        return response()->json(['data' => $row->only(['id','level_id','locale','name'])]);
    }

    // PUT /api/admin/programs/{program}/translations
    public function upsertProgramTranslation(Program $program, Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }
        $data = $request->validate([
            'locale' => ['required','string','max:10'],
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
        ]);
        $row = ProgramTranslation::updateOrCreate(
            ['program_id' => $program->id, 'locale' => $data['locale']],
            ['title' => $data['title'], 'description' => $data['description'] ?? null]
        );
        return response()->json(['data' => $row->only(['id','program_id','locale','title','description'])]);
    }

    // PUT /api/admin/meetings/{meeting}/translations
    public function upsertMeetingTranslation(Meeting $meeting, Request $request): JsonResponse
    {
        if ($resp = $this->ensureAdmin($request)) {
            return $resp;
        }
        $data = $request->validate([
            'locale' => ['required','string','max:10'],
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
        ]);
        $row = MeetingTranslation::updateOrCreate(
            ['meeting_id' => $meeting->id, 'locale' => $data['locale']],
            ['title' => $data['title'], 'description' => $data['description'] ?? null]
        );
        return response()->json(['data' => $row->only(['id','meeting_id','locale','title','description'])]);
    }
}
