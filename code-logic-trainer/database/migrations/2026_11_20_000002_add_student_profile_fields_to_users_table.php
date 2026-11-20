<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('age')->nullable()->after('personal_email');
            $table->string('year_level', 50)->nullable()->after('age');
            $table->string('student_id', 100)->nullable()->unique()->after('year_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
            $table->dropColumn(['age', 'year_level', 'student_id']);
        });
    }
};
