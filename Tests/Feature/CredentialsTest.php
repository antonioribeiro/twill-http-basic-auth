<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * What counts as the right credentials.
 */
class CredentialsTest extends TestCase
{
    public function test_the_right_password_with_another_user_is_refused(): void
    {
        $this->row('admin.example.test', 'user', 'secret');

        $this->visit('admin.example.test', 'someone', 'secret')->assertStatus(401);
    }

    public function test_the_credentials_of_another_hosts_row_do_not_open_this_one(): void
    {
        $this->row('admin.example.test', 'adminuser', 'adminsecret');
        $this->row('staging.example.test', 'staginguser', 'stagingsecret');

        $this->visit('admin.example.test', 'staginguser', 'stagingsecret')->assertStatus(401);
        $this->visit('staging.example.test', 'staginguser', 'stagingsecret')->assertOk();
    }

    public function test_an_empty_password_is_refused(): void
    {
        $this->row('admin.example.test', 'user', 'secret');

        $this->visit('admin.example.test', 'user', '')->assertStatus(401);
    }
}
