<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow questions to belong to a live quiz (quiz_id) instead of a
        // self-study quiz_session. Existing self-study questions keep their id.
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('quiz_session_id')->nullable()->change();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('quiz_id')->nullable()->after('quiz_session_id')
                ->constrained('quizzes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quiz_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('quiz_session_id')->nullable(false)->change();
        });
    }
};
