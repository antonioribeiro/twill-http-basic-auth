<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Repositories\TwillHttpBasicAuthRepository;

/**
 * Twill keeps a revision of what an admin form submits, as JSON. A password typed in this form must not end up in it.
 */
class RevisionsTest extends TestCase
{
    public function test_a_typed_password_is_not_kept_in_a_revision(): void
    {
        app(TwillHttpBasicAuthRepository::class)->create([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'typed-in-the-form',
            'published' => true,
        ]);

        $this->assertStringNotContainsString(
            'typed-in-the-form',
            (string) json_encode(DB::table('twill_basic_auth_revisions')->get()),
        );
    }
}
