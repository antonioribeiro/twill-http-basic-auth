<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * Which hosts are protected: the admin host but not the naked domain, every host, or none.
 */
class HostProtectionTest extends TestCase
{
    public function test_the_naked_domain_is_open_when_only_the_admin_host_has_a_row(): void
    {
        $this->row('admin.example.test');

        $this->visit('example.test')->assertOk()->assertSee('ok');
    }

    public function test_the_admin_host_answers_401_with_www_authenticate_without_credentials(): void
    {
        $this->row('admin.example.test');

        $this->visit('admin.example.test')->assertStatus(401)->assertHeader('WWW-Authenticate');
    }

    public function test_the_admin_host_answers_401_with_a_wrong_password(): void
    {
        $this->row('admin.example.test', 'user', 'secret');

        $this->visit('admin.example.test', 'user', 'wrong')->assertStatus(401);
    }

    public function test_the_admin_host_passes_with_the_right_credentials(): void
    {
        $this->row('admin.example.test', 'user', 'secret');

        $this->visit('admin.example.test', 'user', 'secret')->assertOk()->assertSee('ok');
    }

    public function test_a_row_for_star_protects_every_host(): void
    {
        $this->row('*');

        $this->visit('example.test')->assertStatus(401);
        $this->visit('admin.example.test')->assertStatus(401);
        $this->visit('example.test', 'user', 'secret')->assertOk();
    }

    public function test_env_credentials_protect_every_host(): void
    {
        $this->envCredentials('envuser', 'envsecret');

        $this->visit('example.test')->assertStatus(401);
        $this->visit('admin.example.test')->assertStatus(401);
        $this->visit('anything.example.org', 'envuser', 'envsecret')->assertOk();
    }

    public function test_env_credentials_win_over_database_rows(): void
    {
        $this->row('admin.example.test', 'rowuser', 'rowsecret');
        $this->envCredentials('envuser', 'envsecret');

        $this->visit('admin.example.test', 'envuser', 'envsecret')->assertOk();
        $this->visit('admin.example.test', 'rowuser', 'rowsecret')->assertStatus(401);
    }

    public function test_a_disabled_package_protects_nothing(): void
    {
        $this->envCredentials();
        config(['twill-http-basic-auth.enabled' => false]);

        $this->visit('example.test')->assertOk();
    }

    public function test_a_row_that_is_not_published_protects_nothing(): void
    {
        $this->row('admin.example.test', 'user', 'secret', false);

        $this->visit('admin.example.test')->assertOk();
    }
}
