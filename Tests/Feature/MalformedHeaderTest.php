<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * What a client can send in the Authorization header, wrongly or oddly.
 */
class MalformedHeaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->row('admin.example.test', 'user', 'se:cret ü€');
    }

    public function test_authorization_basic_with_no_payload_is_401(): void
    {
        $this->visit('admin.example.test', headers: ['Authorization' => 'Basic'])->assertStatus(401);
    }

    public function test_bad_base64_is_401(): void
    {
        $this->visit('admin.example.test', headers: ['Authorization' => 'Basic !!!not-base64!!!'])->assertStatus(401);
    }

    public function test_a_payload_with_no_colon_is_401(): void
    {
        $this->visit(
            'admin.example.test',
            headers: ['Authorization' => 'Basic ' . base64_encode('useronly')],
        )->assertStatus(401);
    }

    public function test_another_scheme_is_401(): void
    {
        $this->visit('admin.example.test', headers: ['Authorization' => 'Bearer abc'])->assertStatus(401);
    }

    public function test_a_colon_and_non_ascii_characters_in_the_password_are_accepted(): void
    {
        $this->visit(
            'admin.example.test',
            headers: ['Authorization' => 'Basic ' . base64_encode('user:se:cret ü€')],
        )->assertOk();
    }

    public function test_an_empty_username_is_401(): void
    {
        $this->visit(
            'admin.example.test',
            headers: ['Authorization' => 'Basic ' . base64_encode(':se:cret ü€')],
        )->assertStatus(401);
    }

    public function test_head_is_protected_too(): void
    {
        $this->visit('admin.example.test', method: 'HEAD')->assertStatus(401);
    }
}
