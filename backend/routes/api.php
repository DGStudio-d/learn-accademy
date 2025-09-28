<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TranslationsController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Levels
Route::get('/levels', [LevelController::class, 'index']);
Route::get('/languages/{language}/levels', [LevelController::class, 'byLanguage']);

// Languages
Route::get('/languages', [LanguageController::class, 'index']);
Route::get('/languages/{language}', [LanguageController::class, 'show']);

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Programs (student only)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/programs', [ProgramController::class, 'index']);
    // Quizzes - teacher/admin create
    Route::post('/programs/{program}/quizzes', [QuizController::class, 'store']);
    // Quizzes - student attempts
    Route::post('/quizzes/{quiz}/attempts', [QuizController::class, 'attempt']);
    // Quizzes - list quizzes for a program
    Route::get('/programs/{program}/quizzes', [QuizController::class, 'programQuizzes']);
    // Quizzes - management
    Route::patch('/quizzes/{quiz}', [QuizController::class, 'update']);
    Route::put('/quizzes/{quiz}/questions', [QuizController::class, 'upsertQuestions']);
    Route::delete('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'deleteQuestion']);
    // Quizzes - analytics (teacher/admin)
    Route::get('/quizzes/{quiz}/attempts', [QuizController::class, 'attemptsForQuiz']);
    Route::get('/programs/{program}/attempts', [QuizController::class, 'attemptsForProgram']);

    // Teachers
    Route::post('/teachers/{teacher}/image', [TeacherController::class, 'uploadImage']);
    Route::post('/teachers/{teacher}/languages', [TeacherController::class, 'assignLanguages']);

    // Meetings
    Route::post('/programs/{program}/meetings', [MeetingController::class, 'store']);
    Route::get('/programs/{program}/meetings', [MeetingController::class, 'index']);

    // Admin
    Route::post('/languages', [AdminController::class, 'createLanguage']);
    Route::patch('/admin/settings', [AdminController::class, 'updateSettings']);
    Route::get('/admin/programs/{program}/students', [AdminController::class, 'programStudents']);
    // Admin translations upsert
    Route::put('/admin/languages/{language}/translations', [AdminController::class, 'upsertLanguageTranslation']);
    Route::put('/admin/levels/{level}/translations', [AdminController::class, 'upsertLevelTranslation']);
    Route::put('/admin/programs/{program}/translations', [AdminController::class, 'upsertProgramTranslation']);
    Route::put('/admin/meetings/{meeting}/translations', [AdminController::class, 'upsertMeetingTranslation']);
});

// Quizzes - guest attempts (if enabled)
Route::post('/quizzes/{quiz}/guest-attempt', [QuizController::class, 'guestAttempt']);
// Quizzes - show quiz (guest allowed if enabled)
Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);

// Teachers (guest if enabled)
Route::get('/languages/{language}/teachers', [TeacherController::class, 'byLanguage']);

// Translations
Route::get('/translations/{locale}', [TranslationsController::class, 'show']);
