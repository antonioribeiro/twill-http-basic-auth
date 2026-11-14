<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * A protected host whose credentials cannot be used must stay closed: a mistake in the admin or in the .env never opens a site.
 */
class FailClosedTest extends TestCase
{
    public function test_a_row_with_a_null_password_answers_503_not_200(): void
    {
        $this->row('admin.example.test', 'user', null);

        $this->visit('admin.example.test')->assertStatus(503);
        $this->visit('admin.example.test', 'user', '')->assertStatus(503);
    }

    public function test_a_row_with_no_username_answers_503(): void
    {
        $this->row('admin.example.test', null, 'secret');

        $this->visit('admin.example.test', 'user', 'secret')->assertStatus(503);
    }

    public function test_an_undecryptable_password_answers_503(): void
    {
        $this->row('admin.example.test');
        // a plain value, written by hand or by an old version: it is not what Crypt produces
        DB::table('twill_basic_auth')->update(['password' => 'plain-text']);

        $this->visit('admin.example.test', 'user', 'plain-text')->assertStatus(503);
    }

    public function test_half_set_env_credentials_answer_503(): void
    {
        config([
            'twill-http-basic-auth.enabled' => true,
            'twill-http-basic-auth.keys.username' => 'envuser',
            'twill-http-basic-auth.keys.password' => null,
        ]);

        $this->visit('example.test')->assertStatus(503);
        $this->visit('example.test', 'envuser', '')->assertStatus(503);
    }

    public function test_the_misconfiguration_is_logged_and_not_shown_to_the_visitor(): void
    {
        $log = Log::spy();

        $this->row('admin.example.test', 'user', null);

        $response = $this->visit('admin.example.test');

        $response->assertStatus(503);
        $this->assertStringNotContainsString('user', (string) $response->getContent());
        $log->shouldHaveReceived('error')->once();
    }
}
