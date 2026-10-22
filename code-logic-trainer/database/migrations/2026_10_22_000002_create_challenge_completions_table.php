<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('code_challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('passed_tests');
            $table->unsignedInteger('total_tests');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'code_challenge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_completions');
    }
};
