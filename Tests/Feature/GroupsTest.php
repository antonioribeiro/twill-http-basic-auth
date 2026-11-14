<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * The middleware must sit on every group that serves the protected host: the front (`web`), the API, and the group Twill's admin
 * runs on. A host that is closed on its pages but open on its API or its admin is not closed.
 */
class GroupsTest extends TestCase
{
    public function test_the_api_of_a_protected_host_is_protected(): void
    {
        $this->envCredentials();

        $this->visit('example.test', path: '/api/ping')->assertStatus(401);
        $this->visit('example.test', 'envuser', 'envsecret', path: '/api/ping')->assertOk()->assertSee('pong');
    }

    public function test_the_twill_admin_group_of_a_protected_host_is_protected(): void
    {
        $this->row('admin.example.test');

        $this->visit('admin.example.test', path: '/admin-area')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'secret', path: '/admin-area')->assertOk()->assertSee('admin');
    }

    public function test_the_admin_and_the_api_of_an_unprotected_host_are_untouched(): void
    {
        $this->row('admin.example.test');

        $this->visit('example.test', path: '/api/ping')->assertOk();
        $this->visit('example.test', path: '/admin-area')->assertOk();
    }

    public function test_the_password_is_asked_before_the_applications_own_login_redirect(): void
    {
        $this->row('admin.example.test');

        // not a 302 to the login page: the visitor has not even been let in to see that there is one
        $this->visit('admin.example.test', path: '/admin-private')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'wrong', path: '/admin-private')->assertStatus(401);
        $this->visit('admin.example.test', 'user', 'secret', path: '/admin-private')->assertRedirect('/login');
    }
}
