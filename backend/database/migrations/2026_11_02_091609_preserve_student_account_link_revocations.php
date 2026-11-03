<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_account_links', function (Blueprint $table): void {
            $table->dropUnique(['student_id']);
            $table->dropUnique(['user_id']);
            $table->timestamp('revoked_at')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX student_account_active_student ON student_account_links (student_id) WHERE revoked_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX student_account_active_user ON student_account_links (user_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        if (DB::table('student_account_links')->whereNotNull('revoked_at')->exists()) {
            throw new RuntimeException('Retained account-link history prevents this rollback.');
        }
        Schema::table('student_account_links', function (Blueprint $table): void {
            $table->dropIndex('student_account_active_student');
            $table->dropIndex('student_account_active_user');
            $table->dropColumn('revoked_at');
            $table->unique('student_id');
            $table->unique('user_id');
        });
    }
};
