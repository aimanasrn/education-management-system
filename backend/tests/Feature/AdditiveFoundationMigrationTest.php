<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdditiveFoundationMigrationTest extends TestCase
{
    public function test_foundation_migration_preserves_existing_users_and_backfills_their_roles(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.legacy_foundation_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        try {
            DB::setDefaultConnection('legacy_foundation_test');
            Schema::clearResolvedInstance('db.schema');
            $this->app->forgetInstance('db.schema');
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('role');
            });
            DB::table('users')->insert([
                ['id' => 1, 'name' => 'Existing admin', 'role' => 'admin'],
                ['id' => 2, 'name' => 'Existing teacher', 'role' => 'teacher'],
                ['id' => 3, 'name' => 'Existing student', 'role' => 'student'],
            ]);
            $migration = require database_path('migrations/2026_11_02_041126_create_campus_and_academic_foundations.php');
            $migration->up();
            $this->assertSame(3, DB::table('users')->count());
            $this->assertSame('Existing admin', DB::table('users')->where('id', 1)->value('name'));
            $this->assertEquals([1 => 'admin', 2 => 'teacher', 3 => 'student'], DB::table('user_role_assignments')->pluck('role', 'user_id')->all());
            $this->assertSame(3, DB::table('users')->where('is_active', true)->count());
            $this->assertSame(0, DB::table('campuses')->count());
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('legacy_foundation_test');
            Schema::clearResolvedInstance('db.schema');
            $this->app->forgetInstance('db.schema');
        }
    }
}
