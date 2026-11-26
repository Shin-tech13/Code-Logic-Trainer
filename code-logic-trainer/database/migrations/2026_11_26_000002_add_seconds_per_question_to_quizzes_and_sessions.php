<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedTinyInteger('seconds_per_question')->default(20);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('seconds_per_question')->default(20);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('seconds_per_question');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('seconds_per_question');
        });
    }
};
