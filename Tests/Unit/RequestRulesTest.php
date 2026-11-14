<?php

namespace A17\TwillHttpBasicAuth\Tests\Unit;

use Illuminate\Support\Facades\Validator;
use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Http\Requests\TwillHttpBasicAuthRequest;

class RequestRulesTest extends TestCase
{
    public function test_the_admin_form_needs_a_domain_and_lets_the_password_be_empty(): void
    {
        foreach (['POST', 'PUT'] as $method) {
            $rules = TwillHttpBasicAuthRequest::create('/', $method)->rules();

            $this->assertTrue(
                Validator::make(
                    ['domain' => 'admin.example.test', 'username' => 'user', 'password' => ''],
                    $rules,
                )->passes(),
                "{$method}: an empty password keeps the stored one",
            );
            $this->assertTrue(
                Validator::make(['username' => 'user'], $rules)->fails(),
                "{$method}: the domain is required",
            );
        }
    }

    public function test_only_a_host_name_or_a_star_is_a_domain(): void
    {
        $rules = TwillHttpBasicAuthRequest::create('/', 'POST')->rules();

        foreach (['*', 'example.test', 'admin.example.test', 'xn--nxasmq6b.example.test'] as $domain) {
            $this->assertTrue(Validator::make(['domain' => $domain], $rules)->passes(), $domain);
        }

        foreach (['*.example.test', 'a.test, b.test', 'exa mple.test', '-bad.test', 'http://'] as $domain) {
            $this->assertTrue(Validator::make(['domain' => $domain], $rules)->fails(), $domain);
        }
    }

    public function test_a_domain_is_typed_once(): void
    {
        $this->row('admin.example.test');

        $rules = TwillHttpBasicAuthRequest::create('/', 'POST')->rules();

        $this->assertTrue(Validator::make(['domain' => 'Admin.Example.Test.'], $rules)->fails());
        $this->assertTrue(Validator::make(['domain' => 'staging.example.test'], $rules)->passes());
    }
}
