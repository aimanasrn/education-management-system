<?php

namespace Tests\Feature;

use App\JwtAccess;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class JwtAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('a', 64), 'jwt.issuer' => 'ems-api', 'jwt.audience' => 'ems-api', 'jwt.ttl_minutes' => 15]);
    }

    private function login(?User $user = null): array
    {
        $user ??= User::factory()->admin()->create();
        $this->app['auth']->forgetGuards();
        $body = $this->postJson('/api/jwt/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

        return [$user, $body['token']];
    }

    private function getWith(string $token, string $path = '/api/user')
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($path);
    }

    public function test_jwt_uses_short_lived_minimal_signed_claims_and_existing_permission_checks(): void
    {
        $user = User::factory()->create();
        [$user,$token] = $this->login($user);
        $claims = (array) JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        $this->assertSame((string) $user->id, $claims['sub']);
        $this->assertSame(900, $claims['exp'] - $claims['iat']);
        foreach (['email', 'name', 'roles', 'password'] as $key) {
            $this->assertArrayNotHasKey($key, $claims);
        }
        $this->getWith($token)->assertOk()->assertJsonPath('data.id', $user->id);
        $this->getWith($token, '/api/system/users')->assertForbidden();
    }

    public function test_tampered_expired_future_wrong_issuer_audience_and_algorithm_tokens_are_rejected(): void
    {
        [, $token] = $this->login();
        $claims = (array) JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        $variants = [['iss' => 'other'], ['aud' => 'other'], ['exp' => time() - 1], ['iat' => time() + 100, 'nbf' => time() + 100], ['token_use' => 'refresh'], ['sub' => '0'], ['jti' => str_repeat('9', 100)], ['exp' => null]];
        foreach ($variants as $changes) {
            $bad = JWT::encode([...$claims, ...$changes], config('jwt.secret'), 'HS256');
            $this->getWith($bad)->assertUnauthorized();
        }
        $this->getWith(JWT::encode($claims, config('jwt.secret'), 'HS384'))->assertUnauthorized();
        $parts = explode('.', $token);
        $parts[1] = rtrim(strtr(base64_encode(json_encode([...$claims, 'sub' => '999'])), '+/', '-_'), '=');
        $this->getWith(implode('.', $parts))->assertUnauthorized();
        $this->getWith(str_repeat('a', 5000))->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_jwt_and_refresh_rotates_its_session(): void
    {
        [$user,$token] = $this->login();
        [, $other] = $this->login($user);
        $this->app['auth']->forgetGuards();
        $new = $this->withToken($token)->postJson('/api/jwt/refresh')->assertOk()->json('token');
        $this->getWith($token)->assertUnauthorized();
        $this->getWith($new)->assertOk();
        $this->getWith($other)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($new)->postJson('/api/logout')->assertNoContent();
        $this->getWith($new)->assertUnauthorized();
        $this->getWith($other)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_refresh_preserves_the_absolute_eight_hour_session_limit(): void
    {
        $user = User::factory()->create();
        $authenticatedAt = time() - 8 * 3600 + 60;
        $issued = app(JwtAccess::class)->issue($user, $authenticatedAt);
        $claims = (array) JWT::decode($issued['token'], new Key(config('jwt.secret'), 'HS256'));
        $this->assertSame($authenticatedAt + 8 * 3600, $claims['exp']);
        $this->assertLessThanOrEqual(60, $issued['expires_in']);
        $this->app['auth']->forgetGuards();
        $refreshed = $this->withToken($issued['token'])->postJson('/api/jwt/refresh')->assertOk()->json('token');
        $newClaims = (array) JWT::decode($refreshed, new Key(config('jwt.secret'), 'HS256'));
        $this->assertSame($authenticatedAt, $newClaims['auth_time']);
        $this->assertSame($claims['exp'], $newClaims['exp']);
        $this->getWith($issued['token'])->assertUnauthorized();
        $this->getWith($refreshed)->assertOk();
        $before = PersonalAccessToken::count();
        try {
            app(JwtAccess::class)->issue($user, time() - 8 * 3600);
            $this->fail('An expired session cannot be extended.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
        $this->assertSame($before, PersonalAccessToken::count());
    }

    public function test_web_login_tokens_still_work_and_cannot_be_refreshed_as_jwts(): void
    {
        $user = User::factory()->admin()->create();
        $web = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->json('token');
        $this->getWith($web)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($web)->postJson('/api/jwt/refresh')->assertUnauthorized();
    }

    public function test_disabled_accounts_and_access_changes_revoke_jwt_sessions(): void
    {
        $teacher = User::factory()->create();
        $teacher->roleAssignments()->delete();
        $teacher->roleAssignments()->create(['role' => 'teacher']);
        $teacher->unsetRelation('roleAssignments');
        [, $token] = $this->login($teacher);
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('test')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->putJson('/api/system/users/'.$teacher->id, ['name' => $teacher->name, 'email' => $teacher->email, 'roles' => ['student'], 'campus_ids' => [], 'is_active' => true])->assertOk();
        $this->getWith($token)->assertUnauthorized();
        [, $new] = $this->login($teacher->fresh());
        $teacher->forceFill(['is_active' => false])->save();
        $this->getWith($new)->assertUnauthorized();
    }

    public function test_a_jwt_cannot_reference_an_ordinary_sanctum_token_and_missing_config_fails_closed(): void
    {
        [$user,$token] = $this->login();
        $claims = (array) JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        $web = $user->createToken('school-web', ['*'], now()->addMinutes(15))->accessToken;
        $this->getWith(JWT::encode([...$claims, 'jti' => (string) $web->id], config('jwt.secret'), 'HS256'))->assertUnauthorized();
        config(['jwt.secret' => '']);
        $before = PersonalAccessToken::count();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/jwt/login', ['email' => $user->email, 'password' => 'password'])->assertServiceUnavailable();
        $this->assertSame($before, PersonalAccessToken::count());
    }

    public function test_jwt_and_web_login_share_throttling_and_bad_password_responses(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($i % 2 ? '/api/login' : '/api/jwt/login', ['email' => 'unknown@example.test', 'password' => 'bad'])->assertUnprocessable();
        }
        $this->postJson('/api/jwt/login', ['email' => 'unknown@example.test', 'password' => 'bad'])->assertTooManyRequests();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
