<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth; // <-- Added this import
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\StudentLiveQuizController;
use App\Http\Controllers\ProfessorQuizController;
use App\Http\Controllers\ProfessorSessionController;
use App\Http\Controllers\WhitelistController;
use App\Http\Controllers\ProfileController;

// Homepage Root Route (Smart Redirect)
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->role === 'professor'
        ? redirect()->route('professor.dashboard')
        : redirect()->route('student.dashboard');
});

// Catch default Breeze 'dashboard' redirect and send them to the right dashboard
Route::get('/dashboard', function () {
    return Auth::user()->role === 'professor'
        ? redirect()->route('professor.dashboard')
        : redirect()->route('student.dashboard');
})->middleware(['auth'])->name('dashboard');

// ===================== PROFESSOR ROUTES =====================
Route::middleware(['auth', 'professor'])->prefix('professor')->name('professor.')->group(function () {
    Route::get('/dashboard', [ProfessorQuizController::class, 'index'])->name('dashboard');

    // Student whitelist management
    Route::get('/whitelist', [WhitelistController::class, 'index'])->name('whitelist.index');
    Route::post('/whitelist', [WhitelistController::class, 'store'])->name('whitelist.store');
    Route::delete('/whitelist/{student}', [WhitelistController::class, 'destroy'])->name('whitelist.destroy');

    // PDF upload + AI quiz generation
    Route::post('/quizzes/generate', [ProfessorQuizController::class, 'uploadAndGenerate'])->name('quizzes.generate');
    Route::get('/quizzes/{quiz}', [ProfessorQuizController::class, 'show'])->name('quizzes.show');
    Route::delete('/quizzes/{quiz}', [ProfessorQuizController::class, 'destroy'])->name('quizzes.destroy');

    // Live quiz session lifecycle
    Route::post('/quizzes/{quiz}/launch', [ProfessorSessionController::class, 'launch'])->name('sessions.launch');
    Route::get('/sessions/{session}/lobby', [ProfessorSessionController::class, 'lobby'])->name('sessions.lobby');
    Route::get('/sessions/{session}/status', [ProfessorSessionController::class, 'lobbyStatus'])->name('sessions.status');
    Route::post('/sessions/{session}/start', [ProfessorSessionController::class, 'start'])->name('sessions.start');
    Route::post('/sessions/{session}/end', [ProfessorSessionController::class, 'end'])->name('sessions.end');
});

// ===================== STUDENT ROUTES (self-study) =====================
Route::middleware(['auth', 'whitelisted'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentQuizController::class, 'index'])->name('dashboard');
    Route::post('/quiz/generate', [StudentQuizController::class, 'generate'])->name('quiz.generate');
    Route::get('/quiz/{session}/play', [StudentQuizController::class, 'play'])->name('play');
    Route::post('/quiz/{session}/submit', [StudentQuizController::class, 'submit'])->name('quiz.submit');
    Route::get('/quiz/{session}/results', [StudentQuizController::class, 'results'])->name('results');
});

// ===================== STUDENT ROUTES (live quiz) =====================
Route::middleware(['auth', 'whitelisted'])->prefix('live')->name('student.live.')->group(function () {
    Route::get('/join', [StudentLiveQuizController::class, 'join'])->name('join');
    Route::post('/join', [StudentLiveQuizController::class, 'joinSession'])->name('join.store');
    Route::get('/session/{session}/lobby', [StudentLiveQuizController::class, 'lobby'])->name('lobby');
    Route::get('/session/{session}/status', [StudentLiveQuizController::class, 'lobbyStatus'])->name('status');
    Route::get('/session/{session}/take', [StudentLiveQuizController::class, 'take'])->name('take');
    Route::post('/session/{session}/submit', [StudentLiveQuizController::class, 'submit'])->name('submit');
    Route::get('/session/{session}/complete', [StudentLiveQuizController::class, 'complete'])->name('complete');
});

// Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// INCLUDE BREEZE AUTH ROUTES
require __DIR__ . '/auth.php';
