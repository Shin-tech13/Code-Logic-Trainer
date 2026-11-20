<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('personal_email')->nullable()->after('email');
            $table->string('account_status')->default('approved')->index()->after('role');
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->after('account_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });

        DB::table('users')
            ->whereNull('personal_email')
            ->whereRaw('LOWER(email) NOT LIKE ?', ['%@codelogic.com'])
            ->update([
                'personal_email' => DB::raw('email'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropIndex(['account_status']);
            $table->dropColumn(['personal_email', 'account_status', 'approved_at']);
        });
    }
};
