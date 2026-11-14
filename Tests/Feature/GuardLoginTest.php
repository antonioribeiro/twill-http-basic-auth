<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Tests\Support\FakeGuard;

/**
 * People who already have an account of the application may pass with it instead of the shared password (`database-login`).
 */
class GuardLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeGuard::$loggedIn = false;

        Auth::extend('fake-driver', fn(): FakeGuard => new FakeGuard());

        config([
            'auth.guards.fake' => ['driver' => 'fake-driver', 'provider' => 'users'],
            'twill-http-basic-auth.database-login.twill' => [
                'enabled' => true,
                'username-column' => 'email',
                'guard' => 'fake',
            ],
            'twill-http-basic-auth.database-login.laravel.enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        FakeGuard::$loggedIn = false;

        parent::tearDown();
    }

    public function test_an_account_of_the_application_passes_with_its_own_password(): void
    {
        $this->envCredentials('shared', 'sharedsecret');

        $this->visit('example.test', 'staff@example.test', 'staffpw')->assertOk();
    }

    public function test_the_shared_password_still_passes(): void
    {
        $this->envCredentials('shared', 'sharedsecret');

        $this->visit('example.test', 'shared', 'sharedsecret')->assertOk();
    }

    public function test_a_wrong_password_for_an_account_is_refused(): void
    {
        $this->envCredentials('shared', 'sharedsecret');

        $this->visit('example.test', 'staff@example.test', 'nope')->assertStatus(401);
        $this->visit('example.test', 'someone@example.test', 'staffpw')->assertStatus(401);
    }

    public function test_when_the_login_is_turned_off_accounts_do_not_pass(): void
    {
        $this->envCredentials('shared', 'sharedsecret');
        config(['twill-http-basic-auth.database-login.twill.enabled' => false]);

        $this->visit('example.test', 'staff@example.test', 'staffpw')->assertStatus(401);
    }

    public function test_a_session_is_not_a_password(): void
    {
        // the password is asked before the session starts, so a guard that says "logged in" counts for nothing here
        $this->envCredentials('shared', 'sharedsecret');
        FakeGuard::$loggedIn = true;

        $this->visit('example.test')->assertStatus(401);
    }

    public function test_the_twill_login_only_lets_published_accounts_in(): void
    {
        $this->envCredentials('shared', 'sharedsecret');
        config(['twill-http-basic-auth.database-login.twill.conditions' => ['published' => 1]]);

        $this->visit('example.test', 'staff@example.test', 'staffpw')->assertOk();

        $this->assertSame(1, FakeGuard::$lastCredentials['published'] ?? null);
    }

    public function test_a_row_decides_for_itself_whether_accounts_may_pass(): void
    {
        $this->row('admin.example.test', 'user', 'secret');
        $this->row('staging.example.test', 'user', 'secret');
        DB::table('twill_basic_auth')
            ->where('domain', 'admin.example.test')
            ->update(['allow_twill_login' => true]);

        $this->visit('admin.example.test', 'staff@example.test', 'staffpw')->assertOk();
        $this->visit('staging.example.test', 'staff@example.test', 'staffpw')->assertStatus(401);
    }

    public function test_a_session_never_opens_a_host_that_does_not_allow_accounts(): void
    {
        $this->row('staging.example.test', 'user', 'secret');
        FakeGuard::$loggedIn = true;

        $this->visit('staging.example.test')->assertStatus(401);
    }
}
