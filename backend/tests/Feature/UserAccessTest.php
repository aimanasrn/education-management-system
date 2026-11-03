<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function supportedRoles(): array
    {
        return array_map(fn (UserRole $role) => [$role], UserRole::cases());
    }

    #[DataProvider('supportedRoles')]
    public function test_every_supported_role_can_sign_in_without_being_promoted(UserRole $role): void
    {
        $user = User::factory()->create();
        $user->roleAssignments()->delete();
        $user->roleAssignments()->create(['role' => $role->value]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.roles', [$role->value])
            ->assertJsonPath('user.permissions', $role->permissions());
        $this->assertSame(UserRole::Student, $user->fresh()->role);
    }

    private function data(array $changes = []): array
    {
        return [...['name' => 'New staff', 'email' => 'staff@example.test', 'password' => 'development-pass-123', 'roles' => ['teacher', 'hr_officer'], 'campus_ids' => [], 'is_active' => true], ...$changes];
    }

    private function existing(User $user, array $changes = []): array
    {
        return [...['name' => $user->name, 'email' => $user->email, 'roles' => $user->roles(), 'campus_ids' => [], 'is_active' => true], ...$changes];
    }

    public function test_admin_creates_multiple_roles_and_assigns_campuses_without_exposing_passwords(): void
    {
        $campus = Campus::factory()->create();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->postJson('/api/system/users', $this->data(['campus_ids' => [$campus->id]]))->assertCreated()
            ->assertJsonPath('data.roles', ['teacher', 'hr_officer'])->assertJsonPath('data.campus_ids', [$campus->id])->assertJsonMissingPath('data.password');
        $user = User::findOrFail($response->json('data.id'));
        $this->assertSame(UserRole::Teacher, $user->role);
        $this->assertTrue($user->hasPermission('hr.view'));
        $this->assertFalse($user->hasPermission('finance.view'));
        $this->assertStringNotContainsString('password', AuditLog::first()->toJson());
        $this->assertStringNotContainsString('staff@example.test', AuditLog::first()->toJson());
    }

    public function test_non_administrators_cannot_list_create_update_users_or_read_audit(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher, 'sanctum');
        $this->getJson('/api/system/users')->assertForbidden();
        $this->getJson('/api/system/users/options')->assertForbidden();
        $this->postJson('/api/system/users', $this->data())->assertForbidden();
        $this->putJson('/api/system/users/'.$teacher->id, $this->existing($teacher))->assertForbidden();
        $this->getJson('/api/system/audit')->assertForbidden();
    }

    public function test_centre_administrator_cannot_escalate_privileges_or_edit_system_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $centre = User::factory()->create();
        $centre->roleAssignments()->delete();
        $centre->roleAssignments()->create(['role' => 'centre_admin']);
        $this->actingAs($centre->fresh(), 'sanctum');
        $this->postJson('/api/system/users', $this->data(['roles' => ['admin']]))->assertForbidden();
        $this->postJson('/api/system/users', $this->data(['roles' => ['centre_admin']]))->assertForbidden();
        $this->putJson('/api/system/users/'.$admin->id, $this->existing($admin))->assertForbidden();
        $this->postJson('/api/system/users', $this->data())->assertCreated();
        $this->getJson('/api/system/users/options')->assertOk()->assertJsonMissing(['value' => 'admin'])->assertJsonMissing(['value' => 'centre_admin']);
        $this->getJson('/api/students')->assertOk();
    }

    public function test_system_administrator_cannot_lock_themselves_out(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');
        $this->putJson('/api/system/users/'.$admin->id, $this->existing($admin, ['roles' => ['teacher']]))->assertUnprocessable()->assertJsonValidationErrors('roles');
        $this->putJson('/api/system/users/'.$admin->id, $this->existing($admin, ['is_active' => false]))->assertUnprocessable();
        $this->assertTrue($admin->fresh()->hasAnyRole('admin'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_role_changes_revoke_sessions_and_disabling_accounts_blocks_login(): void
    {
        $target = User::factory()->teacher()->create();
        $target->createToken('existing');
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->putJson('/api/system/users/'.$target->id, $this->existing($target, ['roles' => ['finance_officer']]))->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->putJson('/api/system/users/'.$target->id, $this->existing($target->fresh(), ['is_active' => false]))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $target->email, 'password' => 'password'])->assertUnprocessable()->assertJsonPath('errors.email.0', 'The email address or password is incorrect.');
        $token = $target->createToken('disabled')->plainTextToken;
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_teacher_campus_or_role_cannot_be_removed_while_classes_are_assigned(): void
    {
        $class = SchoolClass::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $teacher->campuses()->attach($class->campus_id);
        $class->teachers()->attach($teacher);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->putJson('/api/system/users/'.$teacher->id, $this->existing($teacher))->assertUnprocessable()->assertJsonValidationErrors('campus_ids');
        $this->putJson('/api/system/users/'.$teacher->id, $this->existing($teacher, ['campus_ids' => [$class->campus_id], 'roles' => ['student']]))->assertUnprocessable();
        $this->assertDatabaseHas('campus_user', ['user_id' => $teacher->id, 'campus_id' => $class->campus_id]);
    }

    public function test_password_and_role_validation_has_no_side_effects(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->postJson('/api/system/users', $this->data(['password' => 'short', 'roles' => ['root']]))->assertUnprocessable()->assertJsonValidationErrors(['password', 'roles.0']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_legacy_role_updates_still_revoke_administrative_authority(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->role = UserRole::Teacher;
        $admin->save();
        $this->assertSame(['teacher'], $admin->fresh()->roles());
        $this->actingAs($admin->fresh(), 'sanctum')->getJson('/api/students')->assertForbidden();
    }

    public function test_student_and_guardian_dashboards_do_not_expose_campus_or_staff_metrics(): void
    {
        Campus::factory()->create();
        $guardian = User::factory()->create();
        $guardian->roleAssignments()->create(['role' => 'guardian']);
        $this->actingAs($guardian->fresh(), 'sanctum')->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.metrics', []);
    }
}
