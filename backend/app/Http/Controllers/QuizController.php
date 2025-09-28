<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    // GET /api/programs/{program}/quizzes (auth)
    public function programQuizzes(Request $request, Program $program): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = $program->quizzes()->select(['id','program_id','teacher_id','title','description','type','pass_score','guest_access','created_at']);
        if ($request->boolean('with_questions')) {
            $query->with(['questions' => function ($q) {
                $q->select(['id','quiz_id','question_text','choices','points','audio_path']);
            }]);
        }
        $items = $query->orderByDesc('id')->get();
        return response()->json(['data' => $items]);
    }

    // GET /api/quizzes/{quiz} (guest allowed only if settings + quiz.guest_access)
    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $guestAllowed = (bool) optional(Setting::where('key', 'allow_guest_quizzes')->first())->value ?? false;
        if (!auth()->check()) {
            if (!($guestAllowed && $quiz->guest_access)) {
                return response()->json(['message' => 'Guest access to this quiz is disabled'], 403);
            }
        }

        if ($request->boolean('with_questions')) {
            $quiz->load(['questions' => function ($q) {
                $q->select(['id','quiz_id','question_text','choices','points','audio_path']);
            }]);
        }
        return response()->json(['data' => $quiz]);
    }

    // PATCH /api/quizzes/{quiz} (teacher/admin)
    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes','string','max:255'],
            'description' => ['sometimes','nullable','string'],
            'pass_score' => ['sometimes','integer','min:0'],
            'guest_access' => ['sometimes','boolean'],
        ]);

        $quiz->fill($data);
        $quiz->save();

        return response()->json(['data' => $quiz]);
    }

    // PUT /api/quizzes/{quiz}/questions (teacher/admin)
    // Accepts array of questions with optional id for update, and optional 'audio' key matching uploaded files in audios[]
    public function upsertQuestions(Request $request, Quiz $quiz): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'questions' => ['required','array'],
            'questions.*.id' => ['sometimes','integer','exists:quiz_questions,id'],
            'questions.*.question_text' => ['required','string'],
            'questions.*.choices' => ['nullable','array'],
            'questions.*.correct_answer' => ['nullable','string'],
            'questions.*.points' => ['nullable','integer','min:1'],
            'questions.*.audio' => ['nullable','string'],
            'audios' => ['sometimes','array'],
            'audios.*' => ['file','mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/aac'],
        ]);

        $audioMap = $this->prepareAudioUploads($request);
        $results = [];

        foreach ($payload['questions'] as $q) {
            $audioPath = null;
            if (!empty($q['audio']) && isset($audioMap[$q['audio']])) {
                $audioPath = $audioMap[$q['audio']];
            }

            if (!empty($q['id'])) {
                $question = QuizQuestion::where('id', $q['id'])->where('quiz_id', $quiz->id)->first();
                if (!$question) {
                    // Skip or create depending on preference; we'll skip to avoid cross-quiz updates
                    continue;
                }
                $question->question_text = $q['question_text'];
                $question->choices = $q['choices'] ?? null;
                $question->correct_answer = $q['correct_answer'] ?? null;
                $question->points = $q['points'] ?? 1;
                if ($audioPath !== null) {
                    $question->audio_path = $audioPath;
                }
                $question->save();
                $results[] = $question;
            } else {
                $question = QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => $q['question_text'],
                    'choices' => $q['choices'] ?? null,
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'points' => $q['points'] ?? 1,
                    'audio_path' => $audioPath,
                ]);
                $results[] = $question;
            }
        }

        return response()->json(['data' => collect($results)->map(function ($r) {
            return $r->only(['id','quiz_id','question_text','choices','correct_answer','points','audio_path']);
        })->values()]);
    }

    // DELETE /api/quizzes/{quiz}/questions/{question} (teacher/admin)
    public function deleteQuestion(Request $request, Quiz $quiz, QuizQuestion $question): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($question->quiz_id !== $quiz->id) {
            return response()->json(['message' => 'Question does not belong to this quiz'], 422);
        }
        $question->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // GET /api/quizzes/{quiz}/attempts (teacher/admin)
    // Filters: ?passed=0|1, ?student_id=ID, ?from=YYYY-MM-DD, ?to=YYYY-MM-DD
    public function attemptsForQuiz(Request $request, Quiz $quiz): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->with(['student:id,name,email'])
            ->select(['id','quiz_id','student_id','score','passed','answers','submitted_at','created_at']);

        if ($request->filled('passed')) {
            $q->where('passed', (bool)$request->boolean('passed'));
        }
        if ($request->filled('student_id')) {
            $q->where('student_id', (int)$request->get('student_id'));
        }
        if ($request->filled('from')) {
            $q->whereDate('submitted_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('submitted_at', '<=', $request->get('to'));
        }

        $attempts = $q->orderByDesc('submitted_at')->get();
        return response()->json(['data' => $attempts]);
    }

    // GET /api/programs/{program}/attempts (teacher/admin)
    // Filters: ?passed=0|1, ?student_id=ID, ?from=YYYY-MM-DD, ?to=YYYY-MM-DD, ?quiz_id=ID
    public function attemptsForProgram(Request $request, Program $program): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = QuizAttempt::query()
            ->whereIn('quiz_id', $program->quizzes()->pluck('id'))
            ->with(['student:id,name,email'])
            ->select(['id','quiz_id','student_id','score','passed','answers','submitted_at','created_at']);

        if ($request->filled('quiz_id')) {
            $q->where('quiz_id', (int)$request->get('quiz_id'));
        }
        if ($request->filled('passed')) {
            $q->where('passed', (bool)$request->boolean('passed'));
        }
        if ($request->filled('student_id')) {
            $q->where('student_id', (int)$request->get('student_id'));
        }
        if ($request->filled('from')) {
            $q->whereDate('submitted_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('submitted_at', '<=', $request->get('to'));
        }

        $attempts = $q->orderByDesc('submitted_at')->get();
        return response()->json(['data' => $attempts]);
    }
    // POST /api/programs/{program}/quizzes (teacher)
    public function store(Request $request, Program $program): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Must be teacher or admin
        if (!($user->role === 'teacher' || $user->hasRole('teacher') || $user->role === 'admin' || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'type' => ['required','in:file,inline'],
            'pass_score' => ['required','integer','min:0'],
            'guest_access' => ['sometimes','boolean'],
            'file' => ['required_if:type,file','file'],
            'format' => ['sometimes','in:csv,json'],
            // Optional additional audio files when type=file
            'audios' => ['sometimes','array'],
            'audios.*' => ['file','mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/aac'],
            'questions' => ['required_if:type,inline','array'],
            'questions.*.question_text' => ['required_if:type,inline','string'],
            'questions.*.choices' => ['nullable','array'],
            'questions.*.correct_answer' => ['nullable','string'],
            'questions.*.points' => ['nullable','integer','min:1'],
        ]);

        return DB::transaction(function () use ($data, $program, $user, $request) {
            $quiz = new Quiz();
            $quiz->program_id = $program->id;
            $quiz->teacher_id = $user->id;
            $quiz->title = $data['title'];
            $quiz->description = $data['description'] ?? null;
            $quiz->type = $data['type'];
            $quiz->pass_score = $data['pass_score'];
            $quiz->guest_access = (bool)($data['guest_access'] ?? false);

            if ($data['type'] === 'file') {
                $path = $request->file('file')->store('quizzes', ['disk' => config('filesystems.default')]);
                $quiz->file_path = $path;
            }

            $quiz->save();

            if ($data['type'] === 'inline') {
                foreach ($data['questions'] as $q) {
                    QuizQuestion::create([
                        'quiz_id' => $quiz->id,
                        'question_text' => $q['question_text'],
                        'choices' => $q['choices'] ?? null,
                        'correct_answer' => $q['correct_answer'] ?? null,
                        'points' => $q['points'] ?? 1,
                    ]);
                }
            } elseif ($data['type'] === 'file') {
                $format = $data['format'] ?? $this->inferFormatFromPath($quiz->file_path);
                $audioMap = $this->prepareAudioUploads($request);
                $extracted = $this->extractQuestionsFromFile($request->file('file')->getRealPath(), $format);
                foreach ($extracted as $item) {
                    $audioPath = null;
                    if (!empty($item['audio'])) {
                        $audioFilename = trim((string)$item['audio']);
                        if (isset($audioMap[$audioFilename])) {
                            $audioPath = $audioMap[$audioFilename];
                        }
                    }
                    QuizQuestion::create([
                        'quiz_id' => $quiz->id,
                        'question_text' => $item['question_text'] ?? '',
                        'choices' => $item['choices'] ?? null,
                        'correct_answer' => $item['correct_answer'] ?? null,
                        'points' => (int)($item['points'] ?? 1),
                        'audio_path' => $audioPath,
                    ]);
                }
            }

            return response()->json([
                'data' => $quiz->load('questions:id,quiz_id,question_text,choices,points'),
            ], 201);
        });
    }

    // POST /api/quizzes/{quiz}/attempts (student)
    public function attempt(Request $request, Quiz $quiz): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Must be student
        if (!($user->role === 'student' || $user->hasRole('student'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'answers' => ['required','array'],
        ]);

        [$score, $max] = $this->scoreAnswers($quiz, $payload['answers']);
        $passed = $score >= (int)$quiz->pass_score;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $user->id,
            'score' => $score,
            'passed' => $passed,
            'answers' => $payload['answers'],
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'score' => $score,
                'max' => $max,
                'passed' => $passed,
                'attempt_id' => $attempt->id,
            ],
        ]);
    }

    // POST /api/quizzes/{quiz}/guest-attempt (guest)
    public function guestAttempt(Request $request, Quiz $quiz): JsonResponse
    {
        $settingsAllowed = (bool) optional(Setting::where('key', 'allow_guest_quizzes')->first())->value ?? false;
        if (!$settingsAllowed || !$quiz->guest_access) {
            return response()->json(['message' => 'Guest attempts are disabled'], 403);
        }

        $payload = $request->validate([
            'answers' => ['required','array'],
        ]);

        [$score, $max] = $this->scoreAnswers($quiz, $payload['answers']);
        $passed = $score >= (int)$quiz->pass_score;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => null,
            'score' => $score,
            'passed' => $passed,
            'answers' => $payload['answers'],
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'score' => $score,
                'max' => $max,
                'passed' => $passed,
                'attempt_id' => $attempt->id,
            ],
        ]);
    }

    private function scoreAnswers(Quiz $quiz, array $answers): array
    {
        $questions = $quiz->questions()->get(['id','correct_answer','points']);
        $score = 0; $max = 0;
        foreach ($questions as $q) {
            $max += (int)($q->points ?? 1);
            $given = $answers[$q->id] ?? null;
            if ($given !== null && $q->correct_answer !== null && (string)$given === (string)$q->correct_answer) {
                $score += (int)($q->points ?? 1);
            }
        }
        return [$score, $max];
    }

    private function inferFormatFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['csv','json']) ? $ext : 'csv';
    }

    private function prepareAudioUploads(Request $request): array
    {
        $map = [];
        if ($request->hasFile('audios')) {
            foreach ($request->file('audios') as $file) {
                $stored = $file->store('quiz-audio', ['disk' => config('filesystems.default')]);
                // Match using the original client filename
                $map[$file->getClientOriginalName()] = $stored;
            }
        }
        return $map;
    }

    private function extractQuestionsFromFile(string $realPath, string $format): array
    {
        if ($format === 'json') {
            $json = @file_get_contents($realPath);
            $data = json_decode($json, true);
            if (is_array($data)) {
                return array_map(function ($row) {
                    // Expect keys: question_text, choices (array or json), correct_answer, points, audio
                    if (isset($row['choices']) && is_string($row['choices'])) {
                        $decoded = json_decode($row['choices'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $row['choices'] = $decoded;
                        }
                    }
                    return $row;
                }, $data);
            }
            return [];
        }

        // CSV fallback
        $rows = [];
        if (($handle = fopen($realPath, 'r')) !== false) {
            $header = null;
            while (($data = fgetcsv($handle)) !== false) {
                if ($header === null) {
                    $header = array_map(fn($h) => strtolower(trim($h)), $data);
                    continue;
                }
                $row = [];
                foreach ($data as $i => $val) {
                    $key = $header[$i] ?? 'col'.$i;
                    $row[$key] = $val;
                }
                // Normalize fields
                if (isset($row['choices'])) {
                    $choicesStr = trim((string)$row['choices']);
                    $decoded = json_decode($choicesStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $row['choices'] = $decoded;
                    } else {
                        // Allow pipe-separated choices
                        $row['choices'] = $choicesStr !== '' ? array_map('trim', explode('|', $choicesStr)) : null;
                    }
                }
                if (isset($row['points'])) {
                    $row['points'] = (int)$row['points'];
                }
                $rows[] = $row;
            }
            fclose($handle);
        }
        return $rows;
    }
}
