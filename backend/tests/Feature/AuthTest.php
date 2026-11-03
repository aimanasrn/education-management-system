<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin],
            'teacher' => [UserRole::Teacher],
            'student' => [UserRole::Student],
        ];
    }

    #[DataProvider('roles')]
    public function test_valid_login_issues_an_expiring_token_without_changing_role(UserRole $role): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 00:00:00 UTC'));
        $user = User::factory()->create(['email' => 'member@school.test', 'role' => $role]);

        $response = $this->postJson('/api/login', [
            'email' => ' MEMBER@school.test ',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $response->assertOk()->assertJsonPath('user.role', $role->value)
            ->assertJsonPath('user.email', 'member@school.test')
            ->assertJsonMissingPath('user.password')
            ->assertHeader('Cache-Control', 'no-store, private');
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertNotNull($token);
        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame('2026-11-02T08:00:00+00:00', $token->expires_at->toIso8601String());
        $this->assertSame($role, $user->fresh()->role);
    }

    public function test_missing_credentials_return_422_without_issuing_a_token(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidFields(): array
    {
        return [
            'email format' => ['invalid', 'password', 'email'],
            'email length' => [str_repeat('a', 245).'@school.test', 'password', 'email'],
            'password length' => ['member@school.test', str_repeat('a', 73), 'password'],
            'multibyte password length' => ['member@school.test', str_repeat('é', 37), 'password'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_credentials_return_422(string $email, string $password, string $field): void
    {
        $this->postJson('/api/login', compact('email', 'password'))
            ->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @return array<string, array{string}> */
    public static function unknownOrIncorrectCredentials(): array
    {
        return [
            'unknown account' => ['unknown@school.test'],
            'incorrect password' => ['member@school.test'],
        ];
    }

    #[DataProvider('unknownOrIncorrectCredentials')]
    public function test_unknown_accounts_and_wrong_passwords_return_the_same_422(string $email): void
    {
        User::factory()->create(['email' => 'member@school.test']);

        $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email address or password is incorrect.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_sixth_login_attempt_for_an_account_returns_429(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'limited@school.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => ' LIMITED@school.test ', 'password' => 'wrong'])
            ->assertTooManyRequests();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_current_user_returns_only_safe_account_fields(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
                'roles' => ['admin'],
                'role_labels' => ['System administrator'],
                'permissions' => UserRole::Admin->permissions(),
                'campus_ids' => [],
                'campus_names' => [],
                'is_active' => true,
            ],
        ]);
    }

    public function test_missing_and_invalid_tokens_return_401(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->withToken('invalid-token')->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_expired_token_returns_401(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['*'], now()->subSecond())->plainTextToken;

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token_and_rejects_its_reuse(): void
    {
        $user = User::factory()->admin()->create();
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $this->withToken($current->plainTextToken)->postJson('/api/logout')->assertNoContent();

        $this->assertModelMissing($current->accessToken);
        $this->assertModelExists($other->accessToken);
        $this->app['auth']->forgetGuards();
        $this->withToken($current->plainTextToken)->getJson('/api/user')->assertUnauthorized();
    }
}
