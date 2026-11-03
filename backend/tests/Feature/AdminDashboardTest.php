<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_receives_actual_account_counts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->teacher()->count(2)->create();
        User::factory()->count(3)->create();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertExactJson(['data' => ['administrators' => 1, 'teachers' => 2, 'students' => 3, 'student_records' => 0]]);
    }

    public function test_empty_roles_have_zero_counts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.teachers', 0)
            ->assertJsonPath('data.students', 0);
    }

    public function test_unauthenticated_dashboard_request_returns_401(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    /** @return array<string, array{UserRole}> */
    public static function nonAdminRoles(): array
    {
        return ['teacher' => [UserRole::Teacher], 'student' => [UserRole::Student]];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_dashboard_request_returns_403(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_role_changes_take_effect_for_existing_tokens(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;
        $user->role = UserRole::Teacher;
        $user->save();

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
