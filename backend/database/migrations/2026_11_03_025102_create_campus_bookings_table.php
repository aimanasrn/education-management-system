<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus_bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->unsignedInteger('attendees');
            $table->string('priority', 20)->default('normal');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20)->default('requested');
            $table->string('series_id', 36)->nullable()->index();
            $table->string('decision_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['room_id', 'status', 'starts_at', 'ends_at'], 'room_booking_period');
            $table->index(['campus_id', 'starts_at']);
        });
        Schema::create('campus_booking_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('reason', 1000)->nullable();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_booking_changes');
        Schema::dropIfExists('campus_bookings');
    }
};
