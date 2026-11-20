<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->foreignId('professor_id')->nullable()->change();
            $table->foreignId('student_id')->nullable()->after('professor_id')
                ->constrained('users')->cascadeOnDelete();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('quiz_format')->default('multiple_choice')->after('status');
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('quiz_format')->default('multiple_choice')->after('type');
            $table->foreignId('source_file_id')->nullable()->after('professor_id')
                ->constrained('uploaded_files')->nullOnDelete();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('question_type')->default('multiple_choice')->after('options');
            $table->json('accepted_answers')->nullable()->after('correct_answer');
            $table->boolean('needs_review')->default(false)->after('explanation');
        });
    }

    public function down(): void
    {
        if (DB::table('uploaded_files')->whereNotNull('student_id')->exists()) {
            throw new \RuntimeException('Move or remove student-owned PDF records before rolling back this migration.');
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['question_type', 'accepted_answers', 'needs_review']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_file_id');
            $table->dropColumn('quiz_format');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('quiz_format');
        });

        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_id');
            $table->foreignId('professor_id')->nullable(false)->change();
        });
    }
};
