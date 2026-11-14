<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * The host decides what is protected: no spelling of it, and no forwarded header, may open a protected host.
 */
class HostTricksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->row('admin.example.test');
    }

    public function test_an_uppercase_host_with_a_port_is_still_protected(): void
    {
        $this->visit('Admin.Example.Test:443')->assertStatus(401);
    }

    public function test_a_trailing_dot_is_still_protected(): void
    {
        $this->visit('admin.example.test.')->assertStatus(401);
    }

    public function test_a_spoofed_x_forwarded_host_does_not_open_the_admin_host(): void
    {
        $this->visit('admin.example.test', headers: ['X-Forwarded-Host' => 'example.test'])->assertStatus(401);
    }

    public function test_a_spoofed_x_forwarded_host_does_not_close_the_naked_domain_either(): void
    {
        $this->visit('example.test', headers: ['X-Forwarded-Host' => 'admin.example.test'])->assertOk();
    }

    public function test_a_row_written_with_capitals_and_a_trailing_dot_still_protects_its_host(): void
    {
        $this->row('Staging.Example.Test.');

        $this->visit('staging.example.test')->assertStatus(401);
    }
}
