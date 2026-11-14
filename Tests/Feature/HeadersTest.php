<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

class HeadersTest extends TestCase
{
    public function test_a_401_names_the_realm(): void
    {
        $this->row('admin.example.test');
        config(['twill-http-basic-auth.realm' => 'Staging']);

        $this->visit('admin.example.test')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="Staging"');
    }

    public function test_a_protected_response_is_not_cacheable(): void
    {
        $this->row('admin.example.test', 'user', 'secret');

        foreach (
            [$this->visit('admin.example.test'), $this->visit('admin.example.test', 'user', 'secret')]
            as $response
        ) {
            $cache = (string) $response->headers->get('Cache-Control');

            $this->assertStringContainsString('no-store', $cache);
            $this->assertStringContainsString('private', $cache);
        }
    }

    public function test_an_unprotected_host_is_left_alone(): void
    {
        $this->row('admin.example.test');

        $this->assertStringNotContainsString(
            'no-store',
            (string) $this->visit('example.test')->headers->get('Cache-Control'),
        );
    }

    public function test_a_json_client_gets_a_json_401(): void
    {
        $this->row('admin.example.test');

        $this->visit('admin.example.test', headers: ['Accept' => 'application/json'])
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_a_503_is_not_cacheable(): void
    {
        $this->row('admin.example.test', 'user', null);

        $this->assertStringContainsString(
            'no-store',
            (string) $this->visit('admin.example.test')->headers->get('Cache-Control'),
        );
    }
}
