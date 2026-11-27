<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_participants', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('joined_at');
            $table->timestamp('last_active_at')->nullable()->after('started_at');
            $table->timestamp('submitted_at')->nullable()->after('last_active_at');
            $table->index(
                ['quiz_session_id', 'started_at', 'submitted_at'],
                'session_participants_progress_idx'
            );
        });

        DB::table('session_participants')
            ->whereIn('status', ['in_progress', 'completed'])
            ->update([
                'started_at' => DB::raw('COALESCE(started_at, updated_at, joined_at)'),
                'last_active_at' => DB::raw('COALESCE(last_active_at, updated_at, joined_at)'),
            ]);

        DB::table('session_participants')
            ->where('status', 'completed')
            ->whereNull('submitted_at')
            ->update(['submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table) {
            $table->dropIndex('session_participants_progress_idx');
            $table->dropColumn(['started_at', 'last_active_at', 'submitted_at']);
        });
    }
};
