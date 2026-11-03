<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signInAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->withToken($user->createToken('test')->plainTextToken);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'student_number' => 'STU-0001',
            'full_name' => 'Ada Student',
            'email' => 'ada@school.test',
            'phone' => '+60 12-345 6789',
            'enrollment_date' => '2026-11-02',
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function endpoints(): array
    {
        return [
            'list' => ['GET', '/api/students'],
            'create' => ['POST', '/api/students'],
            'show' => ['GET', '/api/students/{id}'],
            'update' => ['PUT', '/api/students/{id}'],
            'archive' => ['POST', '/api/students/{id}/archive'],
            'restore' => ['POST', '/api/students/{id}/restore'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_student_endpoints_return_401_without_authentication(string $method, string $path): void
    {
        $student = Student::factory()->create();

        $this->json($method, str_replace('{id}', (string) $student->id, $path), $this->payload())
            ->assertUnauthorized();
    }

    /** @return array<string, array{UserRole, string, string}> */
    public static function forbiddenEndpoints(): array
    {
        $cases = [];
        foreach ([UserRole::Teacher, UserRole::Student] as $role) {
            foreach (self::endpoints() as $name => [$method, $path]) {
                $cases[$role->value.' '.$name] = [$role, $method, $path];
            }
        }

        return $cases;
    }

    #[DataProvider('forbiddenEndpoints')]
    public function test_student_endpoints_return_403_for_other_roles(UserRole $role, string $method, string $path): void
    {
        $user = User::factory()->create(['role' => $role]);
        $student = Student::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->json($method, str_replace('{id}', (string) $student->id, $path), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('students', 1);
        $this->assertNotSoftDeleted($student);
    }

    public function test_admin_creates_a_profile_without_creating_a_login_account(): void
    {
        $this->signInAdmin();
        $data = [...$this->payload(), 'student_number' => ' stu-0001 ', 'email' => ' ADA@school.test ',
            'status' => 'archived', 'deleted_at' => '2026-01-01', 'id' => 999, 'role' => 'admin'];

        $this->postJson('/api/students', $data)->assertCreated()
            ->assertJsonPath('data.student_number', 'STU-0001')
            ->assertJsonPath('data.email', 'ada@school.test')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.archived_at', null)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.role');

        $this->assertDatabaseHas('students', [
            'student_number' => 'STU-0001', 'full_name' => 'Ada Student',
            'email' => 'ada@school.test', 'phone' => '+60 12-345 6789', 'deleted_at' => null,
        ]);
        $this->assertSame('2026-11-02', Student::firstOrFail()->enrollment_date->toDateString());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_required_fields_return_422_without_creating_a_record(): void
    {
        $this->signInAdmin();

        $this->postJson('/api/students', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['student_number', 'full_name', 'enrollment_date']);

        $this->assertDatabaseCount('students', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        return [
            'student number format' => ['student_number', 'STU / 1'],
            'student number length' => ['student_number', str_repeat('A', 31)],
            'name length' => ['full_name', str_repeat('A', 256)],
            'email format' => ['email', 'not-an-email'],
            'email length' => ['email', str_repeat('a', 250).'@x.test'],
            'phone format' => ['phone', 'not-a-phone'],
            'phone length' => ['phone', str_repeat('1', 31)],
            'date format' => ['enrollment_date', '02/11/2026'],
            'invalid date' => ['enrollment_date', '2026-02-30'],
            'wrong input type' => ['full_name', ['Ada']],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_return_422(string $field, mixed $value): void
    {
        $this->signInAdmin();

        $this->postJson('/api/students', [...$this->payload(), $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('students', 0);
    }

    /** @return array<string, array{bool}> */
    public static function archiveStates(): array
    {
        return ['active' => [false], 'archived' => [true]];
    }

    #[DataProvider('archiveStates')]
    public function test_duplicate_student_number_is_reserved_in_both_states(bool $archived): void
    {
        $this->signInAdmin();
        Student::factory()->create(['student_number' => 'STU-0001', 'deleted_at' => $archived ? now() : null]);

        $this->postJson('/api/students', [...$this->payload(), 'student_number' => 'stu-0001'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.student_number.0', 'This student number is already in use, including archived records.');

        $this->assertDatabaseCount('students', 1);
    }

    public function test_optional_contacts_can_be_empty(): void
    {
        $this->signInAdmin();

        $this->postJson('/api/students', [...$this->payload(), 'email' => '', 'phone' => ''])
            ->assertCreated()->assertJsonPath('data.email', null)->assertJsonPath('data.phone', null);

        $this->assertDatabaseHas('students', ['student_number' => 'STU-0001', 'email' => null, 'phone' => null]);
    }

    #[DataProvider('archiveStates')]
    public function test_admin_can_view_active_and_archived_profiles(bool $archived): void
    {
        $this->signInAdmin();
        $student = Student::factory()->create(['deleted_at' => $archived ? now() : null]);

        $this->getJson('/api/students/'.$student->id)->assertOk()
            ->assertJsonPath('data.full_name', $student->full_name)
            ->assertJsonPath('data.enrollment_date', '2026-01-05')
            ->assertJsonPath('data.status', $archived ? 'archived' : 'active');
    }

    public function test_admin_updates_a_profile_and_can_keep_its_student_number(): void
    {
        $this->signInAdmin();
        $student = Student::factory()->create(['student_number' => 'STU-0001']);

        $this->putJson('/api/students/'.$student->id, [...$this->payload(), 'full_name' => 'Ada Updated'])
            ->assertOk()->assertJsonPath('data.full_name', 'Ada Updated');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'full_name' => 'Ada Updated', 'student_number' => 'STU-0001']);
    }

    public function test_update_cannot_take_another_student_number(): void
    {
        $this->signInAdmin();
        $student = Student::factory()->create(['student_number' => 'STU-0002']);
        Student::factory()->create(['student_number' => 'STU-0001']);

        $this->putJson('/api/students/'.$student->id, $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors(['student_number']);

        $this->assertSame('STU-0002', $student->fresh()->student_number);
        $this->assertDatabaseCount('students', 2);
    }

    public function test_archived_profile_cannot_be_edited_until_restored(): void
    {
        $this->signInAdmin();
        $student = Student::factory()->archived()->create();

        $this->putJson('/api/students/'.$student->id, $this->payload())->assertConflict()
            ->assertJsonPath('message', 'Restore this student before editing their record.');

        $this->assertSame($student->full_name, $student->fresh()->full_name);
    }

    public function test_archive_preserves_the_record_and_is_idempotent(): void
    {
        $this->signInAdmin();
        $this->freezeTime();
        $student = Student::factory()->create();

        $response = $this->postJson('/api/students/'.$student->id.'/archive')
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->assertSoftDeleted($student);
        $this->assertDatabaseCount('students', 1);
        $this->travel(1)->minutes();
        $this->postJson('/api/students/'.$student->id.'/archive')->assertOk()
            ->assertJsonPath('data.archived_at', $response->json('data.archived_at'));
        $this->assertSame($student->full_name, $student->fresh()->full_name);
    }

    public function test_restore_reactivates_a_profile_and_is_idempotent(): void
    {
        $this->signInAdmin();
        $student = Student::factory()->archived()->create();

        $this->postJson('/api/students/'.$student->id.'/restore')->assertOk()
            ->assertJsonPath('data.status', 'active')->assertJsonPath('data.archived_at', null);
        $this->postJson('/api/students/'.$student->id.'/restore')->assertOk();

        $this->assertNotSoftDeleted($student);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_missing_profile_returns_404(): void
    {
        $this->signInAdmin();

        $this->getJson('/api/students/99999')->assertNotFound();
        $this->postJson('/api/students/99999/restore')->assertNotFound();
    }

    public function test_non_admin_cannot_discover_whether_a_student_id_exists(): void
    {
        $user = User::factory()->teacher()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/students/999999')->assertForbidden();
    }

    public function test_list_defaults_to_active_records_with_stable_pagination(): void
    {
        $this->signInAdmin();
        Student::factory()->count(17)->create();
        Student::factory()->archived()->create();

        $this->getJson('/api/students')->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', 17);
        $this->getJson('/api/students?page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.id', 2);
    }

    /** @return array<string, array{string, int}> */
    public static function statuses(): array
    {
        return ['active' => ['active', 2], 'archived' => ['archived', 1], 'all' => ['all', 3]];
    }

    #[DataProvider('statuses')]
    public function test_status_filters_return_the_correct_records(string $status, int $total): void
    {
        $this->signInAdmin();
        Student::factory()->count(2)->create();
        Student::factory()->archived()->create();

        $this->getJson('/api/students?status='.$status)->assertOk()->assertJsonPath('meta.total', $total);
    }

    /** @return array<string, array{string}> */
    public static function searches(): array
    {
        return ['number' => ['stu-target'], 'name' => ['aDA sCHOLAR'], 'email' => ['ADA@SCHOOL.TEST']];
    }

    #[DataProvider('searches')]
    public function test_search_is_case_insensitive_and_keeps_archive_filter_grouped(string $search): void
    {
        $this->signInAdmin();
        $student = Student::factory()->create(['student_number' => 'STU-TARGET', 'full_name' => 'Ada Scholar', 'email' => 'ada@school.test']);
        Student::factory()->create(['full_name' => 'Other Learner', 'email' => 'other@school.test']);
        Student::factory()->archived()->create(['full_name' => 'Ada Scholar', 'email' => 'ada@school.test']);

        $this->getJson('/api/students?q='.urlencode($search))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $student->id);
    }

    public function test_search_treats_percent_as_literal_text_and_binds_sql_input(): void
    {
        $this->signInAdmin();
        $student = Student::factory()->create(['full_name' => '100% Scholar']);
        Student::factory()->create(['full_name' => '1000 Scholar']);

        $this->getJson('/api/students?q='.urlencode('%'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $student->id);
        $this->getJson('/api/students?q='.urlencode("x%' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    }

    /** @return array<string, array{string, string}> */
    public static function invalidQueries(): array
    {
        return [
            'status' => ['status', 'deleted'],
            'zero page' => ['page', '0'],
            'noninteger page' => ['page', 'abc'],
            'page size limit' => ['per_page', '101'],
            'zero page size' => ['per_page', '0'],
            'long query' => ['q', str_repeat('x', 101)],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_list_query_returns_422(string $key, string $value): void
    {
        $this->signInAdmin();

        $this->getJson('/api/students?'.$key.'='.urlencode($value))
            ->assertUnprocessable()->assertJsonValidationErrors([$key]);
    }

    public function test_dashboard_counts_active_profiles_separately_from_login_accounts(): void
    {
        $this->signInAdmin();
        Student::factory()->count(2)->create();
        Student::factory()->archived()->create();
        User::factory()->count(3)->create();

        $this->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('data.student_records', 2)->assertJsonPath('data.students', 3);
    }
}
