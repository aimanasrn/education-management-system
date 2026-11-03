<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_documents', fn (Blueprint $table) => $table->string('disk', 20)->default('local'));
    }

    public function down(): void
    {
        if (DB::table('admission_documents')->where('disk', '!=', 'local')->exists()) {
            throw new RuntimeException('Move documents back to local storage before removing disk metadata.');
        }
        Schema::table('admission_documents', fn (Blueprint $table) => $table->dropColumn('disk'));
    }
};
