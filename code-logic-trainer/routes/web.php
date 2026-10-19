<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth; // <-- Added this import
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\ProfileController;

// Homepage Root Route (Smart Redirect)
Route::get('/', function () {
    // Changed to Auth::check() to fix the editor warning
    return Auth::check()
        ? redirect()->route('student.dashboard')
        : redirect()->route('login');
});

// Catch default Breeze 'dashboard' redirect and send them to the student dashboard
Route::get('/dashboard', function () {
    return redirect()->route('student.dashboard');
})->middleware(['auth'])->name('dashboard');

// Student Quiz Routes
Route::middleware(['auth'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentQuizController::class, 'index'])->name('dashboard');
    Route::post('/quiz/generate', [StudentQuizController::class, 'generate'])->name('quiz.generate');

    // The Play Route
    Route::get('/quiz/{session}/play', [StudentQuizController::class, 'play'])->name('play');

    Route::post('/quiz/{session}/submit', [StudentQuizController::class, 'submit'])->name('quiz.submit');
});

// Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// INCLUDE BREEZE AUTH ROUTES
require __DIR__ . '/auth.php';
