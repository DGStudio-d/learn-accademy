<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Notifications\NewMeeting;

class MeetingController extends Controller
{
    // POST /api/programs/{program}/meetings (teacher/admin)
    public function store(Request $request, Program $program): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'link' => ['required','string','max:2048'],
            'starts_at' => ['required','date'],
            'timezone' => ['required','string','max:64'],
            'description' => ['nullable','string'],
        ]);

        $meeting = Meeting::create([
            'program_id' => $program->id,
            'teacher_id' => $user->id,
            'title' => $data['title'],
            'link' => $data['link'],
            'starts_at' => $data['starts_at'],
            'timezone' => $data['timezone'],
            'description' => $data['description'] ?? null,
        ]);

        // Notify all enrolled students in the program (email / WhatsApp as per their preferences)
        $program->enrollments()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->each(function ($student) use ($meeting) {
                $student->notify(new NewMeeting($meeting));
            });

        return response()->json(['data' => $meeting], 201);
    }

    // GET /api/programs/{program}/meetings (student)
    public function index(Program $program, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // If student, require granted access on their enrollment
        if ($user->role === 'student' || (method_exists($user, 'hasRole') && $user->hasRole('student'))) {
            $enrollment = $program->enrollments()->where('user_id', $user->id)->first();
            if (!$enrollment || !$enrollment->granted) {
                return response()->json(['message' => 'Access to this program is not granted yet'], 403);
            }
        }
        // Teacher/admin can view without granted check
        $locale = $request->get('locale');
        $query = $program->meetings()->orderBy('starts_at', 'desc')->select(['id','program_id','teacher_id','title','link','starts_at','timezone','description']);
        if ($locale) {
            $query->with(['translations' => function ($t) use ($locale) {
                $t->where('locale', $locale);
            }]);
        }
        $meetings = $query->get();
        $data = $meetings->map(function ($m) use ($locale) {
            $title = $m->title;
            $description = $m->description;
            if ($locale && $m->relationLoaded('translations') && $m->translations->first()) {
                $title = $m->translations->first()->title ?? $title;
                $description = $m->translations->first()->description ?? $description;
            }
            return [
                'id' => $m->id,
                'program_id' => $m->program_id,
                'teacher_id' => $m->teacher_id,
                'title' => $title,
                'link' => $m->link,
                'starts_at' => $m->starts_at,
                'timezone' => $m->timezone,
                'description' => $description,
            ];
        })->values();
        return response()->json(['data' => $data]);
    }
}
