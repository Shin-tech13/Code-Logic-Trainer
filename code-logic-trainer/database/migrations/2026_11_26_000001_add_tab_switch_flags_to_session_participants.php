<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_participants', function (Blueprint $table) {
            $table->unsignedInteger('tab_switch_count')->default(0);
            $table->timestamp('last_tab_switch_at')->nullable();
            $table->string('last_tab_switch_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('session_participants', function (Blueprint $table) {
            $table->dropColumn([
                'tab_switch_count',
                'last_tab_switch_at',
                'last_tab_switch_reason',
            ]);
        });
    }
};
