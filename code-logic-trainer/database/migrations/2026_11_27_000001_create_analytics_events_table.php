<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic')->nullable()->index();
            $table->string('language')->nullable()->index();
            $table->enum('event_type', [
                'question_shown',
                'answer_submitted',
                'answer_changed',
                'attempt_completed',
                'tab_switched',
                'challenge_run',
            ]);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['session_id', 'question_id', 'event_type']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
