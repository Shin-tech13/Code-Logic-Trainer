<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->json('answers')->nullable();
        });

        Schema::table('session_participants', function (Blueprint $table) {
            $table->json('answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table) {
            $table->dropColumn('answers');
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('answers');
        });
    }
};
