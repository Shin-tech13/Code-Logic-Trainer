<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained('quiz_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('joined'); // joined, in_progress, completed
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_session_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_participants');
    }
};
