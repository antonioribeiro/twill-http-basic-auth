<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Log\Events\MessageLogged;
use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * Guessing is slowed down; honest use never is. Only failed attempts count, per visitor (IP address) and per host.
 */
class ThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['twill-http-basic-auth.rate-limiting.attempts-per-minute' => 3]);

        $this->row('admin.example.test', 'user', 'secret');
        $this->row('staging.example.test', 'user', 'secret');
    }

    public function test_valid_logins_never_count(): void
    {
        foreach (range(1, 30) as $ignored) {
            $this->visit('admin.example.test', 'user', 'secret')->assertOk();
        }
    }

    public function test_failed_attempts_up_to_the_limit_then_429_with_retry_after(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
        }

        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(429)->assertHeader('Retry-After');

        // locked for the minute: the right password does not help a visitor who has been guessing
        $this->visit('admin.example.test', 'user', 'secret')->assertStatus(429);
    }

    public function test_a_correct_login_clears_the_counter(): void
    {
        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'secret')->assertOk();

        // two more failures: 4 in total without the clearing, which would be the 429
        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
    }

    public function test_one_ip_cannot_lock_out_another(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->visit('admin.example.test', 'user', 'wrong', ip: '10.0.0.1');
        }

        $this->visit('admin.example.test', 'user', 'wrong', ip: '10.0.0.1')->assertStatus(429);
        $this->visit('admin.example.test', 'user', 'secret', ip: '10.0.0.2')->assertOk();
    }

    public function test_the_same_ip_on_another_host_has_its_own_counter(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->visit('admin.example.test', 'user', 'wrong');
        }

        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(429);
        $this->visit('staging.example.test', 'user', 'wrong')->assertStatus(401);
        $this->visit('staging.example.test', 'user', 'secret')->assertOk();
    }

    public function test_a_429_is_not_cacheable(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->visit('admin.example.test', 'user', 'wrong');
        }

        $this->assertStringContainsString(
            'no-store',
            (string) $this->visit('admin.example.test', 'user', 'wrong')->headers->get('Cache-Control'),
        );
    }

    public function test_an_unprotected_host_is_never_throttled(): void
    {
        foreach (range(1, 20) as $ignored) {
            $this->visit('example.test', 'user', 'wrong')->assertOk();
        }
    }

    public function test_requests_with_no_credentials_do_not_count(): void
    {
        // a browser's first request, a monitor, a bot: each gets a 401 and none of them is a guess
        foreach (range(1, 20) as $ignored) {
            $this->visit('admin.example.test')->assertStatus(401);
        }

        $this->visit('admin.example.test', 'user', 'secret')->assertOk();
    }

    public function test_rotating_the_host_name_does_not_give_a_new_counter_with_the_env_credentials(): void
    {
        $this->envCredentials('envuser', 'envsecret');

        foreach (range(1, 3) as $number) {
            $this->visit("guess{$number}.example.test", 'envuser', 'wrong')->assertStatus(401);
        }

        $this->visit('guess4.example.test', 'envuser', 'wrong')->assertStatus(429);
    }

    public function test_rotating_the_host_name_does_not_give_a_new_counter_with_a_star_row(): void
    {
        $this->row('*', 'star', 'starsecret');

        foreach (range(1, 3) as $number) {
            $this->visit("guess{$number}.example.test", 'star', 'wrong')->assertStatus(401);
        }

        $this->visit('guess4.example.test', 'star', 'wrong')->assertStatus(429);
    }

    public function test_a_failed_attempt_and_the_lockout_are_logged_without_the_credentials(): void
    {
        $messages = [];

        Event::listen(function (MessageLogged $log) use (&$messages): void {
            if ($log->level === 'warning') {
                $messages[] = $log->message;
            }
        });

        foreach (range(1, 4) as $ignored) {
            $this->visit('admin.example.test', 'someuser', 'hunter2-wrong', ip: '10.0.0.9');
        }

        // one line per failure, one for the moment the limit is reached, none for the refused requests that follow
        $failures = array_filter($messages, fn(string $message): bool => str_contains($message, 'failed'));
        $lockouts = array_filter($messages, fn(string $message): bool => str_contains($message, 'locked out'));

        $this->assertCount(3, $failures);
        $this->assertCount(1, $lockouts);

        foreach ($messages as $message) {
            $this->assertStringContainsString('admin.example.test', $message);
            $this->assertStringContainsString('10.0.0.9', $message);
            $this->assertStringNotContainsString('hunter2', $message);
            $this->assertStringNotContainsString('someuser', $message);
        }
    }
}
