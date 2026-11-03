<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('requested_start');
            $table->string('status', 20)->default('draft')->index();
            $table->text('decision_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('student_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index(['campus_id', 'status']);
        });
        Schema::create('admission_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_application_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedInteger('bytes');
            $table->string('mime', 100);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('student_account_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('student_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 60);
            $table->boolean('emergency_contact')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['student_id', 'user_id']);
        });
        Schema::create('student_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('active');
            $table->string('end_reason', 500)->nullable();
            $table->foreignId('previous_enrolment_id')->nullable()->unique()->constrained('student_enrolments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['school_class_id', 'status', 'starts_on', 'ends_on'], 'enrolment_class_period');
            $table->index(['student_id', 'status', 'starts_on', 'ends_on'], 'enrolment_student_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrolments');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('student_account_links');
        Schema::dropIfExists('admission_documents');
        Schema::dropIfExists('admission_applications');
    }
};
