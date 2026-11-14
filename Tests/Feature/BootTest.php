<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;

class BootTest extends TestCase
{
    public function test_the_application_boots_with_the_package_and_nothing_is_protected_until_it_is_configured(): void
    {
        $this->visit('example.test')->assertOk()->assertSee('ok');
    }
}
