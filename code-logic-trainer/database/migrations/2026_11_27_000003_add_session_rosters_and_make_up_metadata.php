<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->boolean('is_make_up')->default(false)->after('type');
            $table->string('make_up_label', 120)->nullable()->after('is_make_up');
            $table->string('roster_mode')->nullable()->after('make_up_label');
            $table->foreignId('classroom_id')->nullable()->after('roster_mode')
                ->constrained('classrooms')->nullOnDelete();
        });

        Schema::create('quiz_session_invitees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255);
            $table->timestamps();

            $table->unique(['quiz_session_id', 'email']);
            $table->index(['quiz_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_session_invitees');

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropColumn(['is_make_up', 'make_up_label', 'roster_mode', 'classroom_id']);
        });
    }
};
