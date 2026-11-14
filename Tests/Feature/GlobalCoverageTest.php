<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * A protected host is protected whole: not only the routes of a middleware group. Image routes, robots.txt, health checks, a
 * dashboard (Horizon) and the pages that do not exist are all behind the password.
 */
class GlobalCoverageTest extends TestCase
{
    public function test_a_route_with_no_middleware_group_is_protected(): void
    {
        $this->row('admin.example.test');

        $this->visit('admin.example.test', path: '/ungrouped')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'secret', path: '/ungrouped')->assertOk()->assertSee('ungrouped');
    }

    public function test_a_page_that_does_not_exist_asks_for_the_password_first(): void
    {
        $this->row('admin.example.test');

        $this->visit('admin.example.test', path: '/there-is-nothing-here')->assertStatus(401);
    }

    public function test_the_automatic_options_answer_is_protected_too(): void
    {
        $this->row('admin.example.test');

        $this->visit('admin.example.test', path: '/ungrouped', method: 'OPTIONS')->assertStatus(401);
    }

    public function test_the_open_host_serves_everything_as_before(): void
    {
        $this->row('admin.example.test');

        $this->visit('example.test', path: '/ungrouped')->assertOk();
        $this->visit('example.test', path: '/there-is-nothing-here')->assertStatus(404);
    }
}
