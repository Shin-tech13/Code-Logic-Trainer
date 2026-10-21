<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-study columns become nullable so the same table can also hold
        // live professor-hosted sessions (type = 'live').
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->string('language')->nullable()->change();
            $table->string('topic')->nullable()->change();
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->foreignId('quiz_id')->nullable()->after('topic')
                ->constrained('quizzes')->onDelete('cascade');
            $table->foreignId('professor_id')->nullable()->after('quiz_id')
                ->constrained('users')->onDelete('cascade');
            $table->string('quiz_code')->nullable()->unique()->after('professor_id');
            $table->string('type')->default('self_study')->after('quiz_code');
            $table->timestamp('started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn(['quiz_id', 'professor_id', 'quiz_code', 'type', 'started_at']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable(false)->change();
            $table->string('language')->nullable(false)->change();
            $table->string('topic')->nullable(false)->change();
        });
    }
};
