<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_creates_an_administrator_with_a_hashed_password(): void
    {
        $this->artisan('app:create-admin', ['email' => ' ADMIN@school.test '])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-test-password')
            ->expectsQuestion('Confirm password', 'a-long-test-password')
            ->expectsOutput('Administrator created: admin@school.test')
            ->assertSuccessful();

        $user = User::where('email', 'admin@school.test')->firstOrFail();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
    }

    public function test_existing_account_cannot_be_overwritten_or_promoted(): void
    {
        $user = User::factory()->create(['email' => 'existing@school.test']);

        $this->artisan('app:create-admin', ['email' => $user->email])->assertFailed();

        $this->assertSame(UserRole::Student, $user->fresh()->role);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_short_password_creates_no_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'admin@school.test'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_mismatched_confirmation_creates_no_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'admin@school.test'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-test-password')
            ->expectsQuestion('Confirm password', 'different-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_identity_creates_no_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'not-an-email'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_exceeding_bcrypt_byte_limit_creates_no_account(): void
    {
        $password = str_repeat('é', 37);

        $this->artisan('app:create-admin', ['email' => 'admin@school.test'])
            ->expectsQuestion('Password (at least 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('The password must not exceed 72 bytes.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_noninteractive_command_does_not_create_an_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'admin@school.test', '--no-interaction' => true])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeding_does_not_create_a_known_default_password(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@demo.ems.test')->firstOrFail();
        $this->assertFalse(Hash::check('password', $admin->password));
        $this->assertFalse(Hash::check('admin12345678', $admin->password));
        Storage::disk('local')->assertExists('demo-credentials.json');
    }
}
