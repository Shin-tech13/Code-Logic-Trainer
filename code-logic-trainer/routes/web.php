<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\StudentLiveQuizController;
use App\Http\Controllers\ProfessorQuizController;
use App\Http\Controllers\ProfessorSessionController;
use App\Http\Controllers\WhitelistController;
use App\Http\Controllers\CodeChallengeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ProfessorStudentController;
use App\Http\Controllers\AnalyticsController;

// Route signed-in users to their role dashboard.
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    abort_unless(
        Auth::user()->isApproved() && in_array(Auth::user()->role, ['admin', 'professor', 'student'], true),
        403,
        'This account is not authorized to access the application.'
    );

    if (Auth::user()->isAdmin()) {
        return redirect()->route('users.index');
    }

    return Auth::user()->isProfessor()
        ? redirect()->route('professor.dashboard')
        : redirect()->route('student.dashboard');
});

// Keep Breeze's dashboard URL role-aware.
Route::get('/dashboard', function () {
    if (Auth::user()->isAdmin()) {
        return redirect()->route('users.index');
    }

    return Auth::user()->isProfessor()
        ? redirect()->route('professor.dashboard')
        : redirect()->route('student.dashboard');
})->middleware(['auth', 'account-approved'])->name('dashboard');

Route::middleware(['auth', 'account-approved', 'user-directory'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [AdminUserController::class, 'index'])->name('index');
    Route::post('/{user}/approve', [AdminUserController::class, 'approve'])->name('approve');
});

Route::middleware(['auth', 'account-approved', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role.update');
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status.update');
});

// Professor routes
Route::middleware(['auth', 'account-approved', 'professor'])->prefix('professor')->name('professor.')->group(function () {
    Route::get('/dashboard', [ProfessorQuizController::class, 'index'])->name('dashboard');
    Route::get('/students', [ProfessorStudentController::class, 'index'])->name('students.index');
    Route::post('/classrooms', [ProfessorStudentController::class, 'storeClassroom'])->name('classrooms.store');
    Route::get('/students/{student}/edit', [ProfessorStudentController::class, 'edit'])->name('students.edit');
    Route::patch('/students/{student}', [ProfessorStudentController::class, 'update'])->name('students.update');

    // Whitelist management
    Route::get('/whitelist', [WhitelistController::class, 'index'])->name('whitelist.index');
    Route::post('/whitelist', [WhitelistController::class, 'store'])->name('whitelist.store');
    Route::delete('/whitelist/{student}', [WhitelistController::class, 'destroy'])->name('whitelist.destroy');

    // Document upload and quiz generation
    Route::post('/quizzes/generate', [ProfessorQuizController::class, 'uploadAndGenerate'])
        ->middleware('throttle:quiz-generation')
        ->name('quizzes.generate');
    Route::get('/quizzes/{quiz}', [ProfessorQuizController::class, 'show'])->name('quizzes.show');
    Route::get('/quizzes/{quiz}/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/quizzes/{quiz}/analytics', [AnalyticsController::class, 'professor'])->name('analytics.show');
    Route::patch('/quizzes/{quiz}/settings', [ProfessorQuizController::class, 'updateSettings'])->name('quizzes.settings.update');
    Route::delete('/quizzes/{quiz}', [ProfessorQuizController::class, 'destroy'])->name('quizzes.destroy');

    // Live quiz sessions
    Route::post('/quizzes/{quiz}/launch', [ProfessorSessionController::class, 'launch'])->name('sessions.launch');
    Route::get('/sessions/{session}/lobby', [ProfessorSessionController::class, 'lobby'])->name('sessions.lobby');
    Route::get('/sessions/{session}/status', [ProfessorSessionController::class, 'lobbyStatus'])->name('sessions.status');
    Route::get('/sessions/{session}/summary', [ProfessorSessionController::class, 'summary'])->name('sessions.summary');
    Route::get('/sessions/{session}/participants', [ProfessorSessionController::class, 'participants'])->name('sessions.participants');
    Route::get('/sessions/{session}/participants/{participant}', [ProfessorSessionController::class, 'participant'])->name('sessions.participants.show');
    Route::get('/sessions/{session}/export', [ProfessorSessionController::class, 'export'])->name('sessions.export');
    Route::patch('/sessions/{session}/timer', [ProfessorSessionController::class, 'updateTimer'])->name('sessions.timer.update');
    Route::post('/sessions/{session}/start', [ProfessorSessionController::class, 'start'])->name('sessions.start');
    Route::post('/sessions/{session}/end', [ProfessorSessionController::class, 'end'])->name('sessions.end');
});

// Student self-study routes
Route::middleware(['auth', 'account-approved', 'student', 'whitelisted'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentQuizController::class, 'index'])->name('dashboard');
    Route::get('/history', [StudentQuizController::class, 'history'])->name('history');
    Route::get('/progress', [AnalyticsController::class, 'student'])->name('progress');
    Route::post('/quiz/generate', [StudentQuizController::class, 'generate'])
        ->middleware('throttle:quiz-generation')
        ->name('quiz.generate');
    Route::post('/quiz/generate-from-document', [StudentQuizController::class, 'generateFromDocument'])
        ->middleware('throttle:quiz-generation')
        ->name('quiz.generate.document');
    Route::get('/quiz/{session}/play', [StudentQuizController::class, 'play'])->name('play');
    Route::post('/quiz/{session}/answer', [StudentQuizController::class, 'answer'])->name('quiz.answer');
    Route::post('/quiz/{session}/submit', [StudentQuizController::class, 'submit'])->name('quiz.submit');
    Route::get('/quiz/{session}/results', [StudentQuizController::class, 'results'])->name('results');

    Route::middleware('student')->group(function () {
        Route::get('/challenges', [CodeChallengeController::class, 'index'])->name('challenges.index');
        Route::get('/challenges/{challenge}', [CodeChallengeController::class, 'show'])->name('challenges.show');
        Route::post('/challenges/{challenge}/run', [CodeChallengeController::class, 'run'])
            ->middleware('throttle:10,1')
            ->name('challenges.run');
    });
});

// Student live-quiz routes
Route::middleware(['auth', 'account-approved', 'student', 'whitelisted'])->prefix('live')->name('student.live.')->group(function () {
    Route::get('/join', [StudentLiveQuizController::class, 'join'])->name('join');
    Route::post('/join', [StudentLiveQuizController::class, 'joinSession'])->name('join.store');
    Route::get('/session/{session}/lobby', [StudentLiveQuizController::class, 'lobby'])->name('lobby');
    Route::get('/session/{session}/status', [StudentLiveQuizController::class, 'lobbyStatus'])->name('status');
    Route::get('/session/{session}/take', [StudentLiveQuizController::class, 'take'])->name('take');
    Route::post('/session/{session}/flag-tab-switch', [StudentLiveQuizController::class, 'flagTabSwitch'])->name('flag-tab-switch');
    Route::post('/session/{session}/progress', [StudentLiveQuizController::class, 'saveProgress'])->name('progress');
    Route::post('/session/{session}/submit', [StudentLiveQuizController::class, 'submit'])->name('submit');
    Route::get('/session/{session}/complete', [StudentLiveQuizController::class, 'complete'])->name('complete');
});

Route::middleware(['auth', 'account-approved'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
