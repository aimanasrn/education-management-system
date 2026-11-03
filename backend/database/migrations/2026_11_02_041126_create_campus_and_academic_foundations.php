<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
        });
        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->unique(['user_id', 'role']);
        });
        DB::table('users')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('user_role_assignments')->insert(['user_id' => $user->id, 'role' => $user->role]);
            }
        });
        Schema::create('access_locks', function (Blueprint $table): void {
            $table->string('name')->primary();
        });
        DB::table('access_locks')->insert(['name' => 'access-control']);
        Schema::create('campuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->json('operating_hours')->nullable();
            $table->string('status', 15)->default('active')->index();
            $table->timestamps();
        });
        Schema::create('campus_user', function (Blueprint $table): void {
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'user_id']);
        });
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->unique(['campus_id', 'code']);
        });
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->json('facilities')->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->unique(['campus_id', 'code']);
        });
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 15)->default('active');
            $table->timestamps();
        });
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->unique(['academic_year_id', 'code']);
        });
        Schema::create('programmes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->unique(['programme_id', 'code']);
        });
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->unique(['campus_id', 'academic_year_id', 'code']);
        });
        Schema::create('class_teachers', function (Blueprint $table): void {
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['school_class_id', 'user_id']);
        });
        Schema::create('campus_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 15);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->index(['campus_id', 'starts_on', 'ends_on']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('subject_type', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 30);
            $table->json('changed_fields');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['campus_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'campus_closures', 'class_teachers', 'school_classes', 'subjects', 'programmes', 'academic_terms', 'academic_years', 'rooms', 'buildings', 'campus_user', 'campuses', 'access_locks', 'user_role_assignments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
