<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_seeder_populates_linked_demo_data_and_private_working_credentials(): void
    {
        Storage::fake('local');
        $this->freezeTime();

        $this->seed(DatabaseSeeder::class);

        foreach (['campuses' => 3, 'buildings' => 6, 'rooms' => 18, 'academic_years' => 2, 'academic_terms' => 6, 'programmes' => 3, 'subjects' => 9, 'school_classes' => 18, 'campus_closures' => 9, 'users' => 25, 'students' => 60, 'audit_logs' => 159] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame(54, Student::count());
        $this->assertSame(6, Student::onlyTrashed()->count());
        $this->assertSame(6, SchoolClass::where('status', 'active')->count());
        $this->assertSame(10, Room::where('status', 'active')->count());
        Storage::disk('local')->assertExists(DemoSeeder::CREDENTIALS_FILE);
        $credentials = json_decode(Storage::disk('local')->get(DemoSeeder::CREDENTIALS_FILE), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(25, $credentials['accounts']);
        $this->assertCount(25, array_unique(array_column($credentials['accounts'], 'password')));

        foreach ($credentials['accounts'] as $email => $account) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue(Hash::check($account['password'], $user->password));
            $this->assertSame($account['roles'], $user->roles());
            $this->assertSame($account['enabled'], $user->is_active);
        }
        $roles = DB::table('user_role_assignments')->distinct()->pluck('role')->sort()->values()->all();
        $expectedRoles = array_map(fn (UserRole $role): string => $role->value, UserRole::cases());
        sort($expectedRoles);
        $this->assertSame($expectedRoles, $roles);

        foreach (SchoolClass::with('room', 'academicTerm', 'subject', 'teachers.roleAssignments', 'teachers.campuses')->get() as $class) {
            $this->assertSame($class->campus_id, $class->room->campus_id);
            $this->assertSame($class->academic_year_id, $class->academicTerm->academic_year_id);
            $this->assertSame($class->programme_id, $class->subject->programme_id);
            $this->assertLessThanOrEqual($class->room->capacity, $class->capacity);
            $this->assertGreaterThan(0, $class->teachers->count());
            foreach ($class->teachers as $teacher) {
                $this->assertTrue($teacher->is_active && $teacher->hasAnyRole('teacher'));
                $this->assertTrue($teacher->campuses->contains('id', $class->campus_id));
            }
        }
        foreach (AcademicTerm::with('academicYear')->get() as $term) {
            $this->assertTrue($term->starts_on->greaterThanOrEqualTo($term->academicYear->starts_on));
            $this->assertTrue($term->ends_on->lessThanOrEqualTo($term->academicYear->ends_on));
        }

        $adminCredentials = $credentials['accounts']['admin@demo.ems.test'];
        $this->postJson('/api/login', ['email' => $adminCredentials['email'], 'password' => $adminCredentials['password']])
            ->assertOk()->assertJsonPath('user.roles', ['admin']);
        $teacher = User::where('email', 'teacher.kl.1@demo.ems.test')->firstOrFail();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/foundation/classes')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/students')->assertForbidden();
        $audit = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('password', $audit);
        $this->assertStringNotContainsString('@demo.ems.test', $audit);
        $this->actingAs(User::where('email', 'admin@demo.ems.test')->firstOrFail(), 'sanctum')
            ->getJson('/api/system/audit')->assertOk()->assertJsonPath('data.0.actor', 'Demo seeder');
    }

    public function test_rerunning_preserves_edits_passwords_role_changes_archives_and_counts(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@demo.ems.test')->firstOrFail();
        $admin->name = 'Renamed demo administrator';
        $admin->password = 'a-new-private-password';
        $admin->save();
        $admin->createToken('existing-session');
        $teacher = User::where('email', 'teacher.kl.1@demo.ems.test')->firstOrFail();
        $teacher->roleAssignments()->delete();
        $teacher->roleAssignments()->create(['role' => 'student']);
        $campus = Campus::where('code', 'DEMO-KL')->firstOrFail();
        $campus->update(['name' => 'Edited campus']);
        Student::where('student_number', 'DEMO-0001')->firstOrFail()->delete();
        $snapshot = $this->snapshot();
        $credentials = Storage::disk('local')->get(DemoSeeder::CREDENTIALS_FILE);

        $this->seed(DemoSeeder::class);

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame($credentials, Storage::disk('local')->get(DemoSeeder::CREDENTIALS_FILE));
        $this->assertTrue(Hash::check('a-new-private-password', $admin->fresh()->password));
        $this->assertSame(['student'], $teacher->fresh()->roles());
        $this->assertNotNull(Student::withTrashed()->where('student_number', 'DEMO-0001')->firstOrFail()->deleted_at);
    }

    public function test_existing_accounts_and_profiles_are_never_overwritten_or_promoted(): void
    {
        Storage::fake('local');
        $existing = User::factory()->create(['email' => 'admin@demo.ems.test']);
        $profile = Student::factory()->archived()->create(['student_number' => 'DEMO-0001']);
        $userBefore = $existing->fresh()->getAttributes();
        $profileBefore = $profile->fresh()->getAttributes();

        $this->seed(DemoSeeder::class);

        $this->assertSame($userBefore, $existing->fresh()->getAttributes());
        $this->assertSame(['student'], $existing->fresh()->roles());
        $this->assertSame(0, $existing->campuses()->count());
        $this->assertSame($profileBefore, $profile->fresh()->getAttributes());
        $credentials = json_decode(Storage::disk('local')->get(DemoSeeder::CREDENTIALS_FILE), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('admin@demo.ems.test', $credentials['accounts']);
    }

    public function test_production_environment_is_rejected_before_creating_records_or_credentials(): void
    {
        Storage::fake('local');
        $this->app->instance('env', 'production');

        try {
            $this->app->make(DemoSeeder::class)->run();
            $this->fail('Production demo seeding must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restricted to local/testing', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('campuses', 0);
        Storage::disk('local')->assertMissing(DemoSeeder::CREDENTIALS_FILE);
    }

    public function test_credentials_storage_failure_rolls_back_all_new_database_records(): void
    {
        $disk = Storage::fake('local');
        $failingDisk = Mockery::mock($disk);
        $failingDisk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);

        try {
            $this->seed(DemoSeeder::class);
            $this->fail('Seeding must fail if credentials cannot be stored.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('credentials', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('campuses', 0);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $data = [];
        foreach (['users', 'user_role_assignments', 'campuses', 'campus_user', 'buildings', 'rooms', 'academic_years', 'academic_terms', 'programmes', 'subjects', 'school_classes', 'class_teachers', 'campus_closures', 'students', 'audit_logs', 'personal_access_tokens'] as $table) {
            $data[$table] = DB::table($table)->get()->toJson();
        }

        return $data;
    }
}
