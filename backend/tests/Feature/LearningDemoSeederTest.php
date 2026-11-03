<?php

namespace Tests\Feature;

use App\Models\AdmissionApplication;
use App\Models\AuditLog;
use App\Models\StudentAccountLink;
use App\Models\StudentEnrolment;
use App\Models\StudentGuardian;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LearningDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_learning_seeder_is_additive_idempotent_and_preserves_changed_links(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->seed(LearningDemoSeeder::class);
        $this->assertDatabaseCount('admission_applications', 4);
        $this->assertDatabaseCount('student_enrolments', 4);
        $this->assertDatabaseCount('student_guardians', 4);
        $this->assertDatabaseCount('student_account_links', 4);
        $this->assertDatabaseCount('admission_documents', 0);
        StudentGuardian::first()->update(['status' => 'inactive']);
        AdmissionApplication::first()->update(['status' => 'withdrawn']);
        $before = [AdmissionApplication::all()->toArray(), StudentGuardian::all()->toArray(), StudentAccountLink::all()->toArray(), StudentEnrolment::all()->toArray(), AuditLog::count()];
        $credentials = Storage::disk('local')->get(DemoSeeder::CREDENTIALS_FILE);
        $this->seed(LearningDemoSeeder::class);
        $this->assertSame($before, [AdmissionApplication::all()->toArray(), StudentGuardian::all()->toArray(), StudentAccountLink::all()->toArray(), StudentEnrolment::all()->toArray(), AuditLog::count()]);
        $this->assertSame($credentials, Storage::disk('local')->get(DemoSeeder::CREDENTIALS_FILE));
    }

    public function test_production_is_rejected_without_writes(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->app->make(LearningDemoSeeder::class)->run();
            $this->fail('Production was not rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('local/testing', $exception->getMessage());
        }
        $this->assertDatabaseCount('student_enrolments', 0);
        $this->assertDatabaseCount('admission_applications', 0);
    }
}
