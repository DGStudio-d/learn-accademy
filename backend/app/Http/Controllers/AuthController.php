<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Carbon;
use App\Notifications\WelcomeStudent;
use App\Notifications\AdminNewStudent;

class AuthController extends Controller
{
    // POST /api/register
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable','string','max:50','unique:users,phone'],
            'language_id' => ['required','integer','exists:languages,id'],
            'level_id' => ['required','integer','exists:levels,id'],
            'preferred_language' => ['nullable','string','max:10'],
            'notify_email' => ['sometimes','boolean'],
            'notify_whatsapp' => ['sometimes','boolean'],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->phone = $data['phone'] ?? null;
        $user->role = 'student';
        $user->preferred_language = $data['preferred_language'] ?? null;
        $user->notify_email = $data['notify_email'] ?? true;
        $user->notify_whatsapp = $data['notify_whatsapp'] ?? false;
        $user->save();
        // assign spatie role
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('student');
        }

        // Auto-enroll to all programs of selected language & level
        $programs = Program::query()
            ->where('language_id', $data['language_id'])
            ->where('level_id', $data['level_id'])
            ->get(['id']);
        $now = now();
        foreach ($programs as $program) {
            Enrollment::firstOrCreate(
                ['user_id' => $user->id, 'program_id' => $program->id],
                ['assigned_at' => $now]
            );
        }

        // Send welcome notification to the new student
        $user->notify(new WelcomeStudent([
            'language_id' => $data['language_id'],
            'level_id' => $data['level_id'],
        ]));

        // Notify admins of new registration
        \App\Models\User::query()
            ->where('role', 'admin')
            ->get()
            ->each(function ($admin) use ($user) {
                $admin->notify(new AdminNewStudent($user));
            });

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    // POST /api/login
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $user = $request->user();
        $token = $user->createToken('api')->plainTextToken;
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
