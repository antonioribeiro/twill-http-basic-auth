<?php

namespace A17\TwillHttpBasicAuth\Tests\Unit;

use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Models\TwillHttpBasicAuth;
use A17\TwillHttpBasicAuth\Support\PublishingRequirements;

/**
 * A domain protected by credentials nobody can type could never be opened: publishing needs a user name and a password, typed now
 * or already stored.
 */
class PublishingRequirementsTest extends TestCase
{
    public function test_a_row_that_is_not_being_published_is_never_refused(): void
    {
        $this->assertFalse(PublishingRequirements::missing(['published' => false], null));
        $this->assertFalse(PublishingRequirements::missing([], null));
    }

    public function test_publishing_with_nothing_typed_and_nothing_stored_is_refused(): void
    {
        $this->assertTrue(PublishingRequirements::missing(['published' => true], null));
        $this->assertTrue(PublishingRequirements::missing(['published' => true, 'username' => 'u'], null));
        $this->assertTrue(PublishingRequirements::missing(['published' => true, 'password' => 'p'], null));
    }

    public function test_publishing_with_both_typed_is_allowed(): void
    {
        $this->assertFalse(
            PublishingRequirements::missing(['published' => true, 'username' => 'u', 'password' => 'p'], null),
        );
    }

    public function test_what_is_already_stored_counts(): void
    {
        $this->row('admin.example.test', 'user', 'secret', published: false);
        $id = TwillHttpBasicAuth::query()->firstOrFail()->id;

        $this->assertFalse(PublishingRequirements::missing(['published' => true], $id));
        $this->assertFalse(PublishingRequirements::missing(['published' => true, 'username' => 'new'], $id));
    }

    public function test_a_stored_row_without_a_password_is_refused(): void
    {
        $this->row('admin.example.test', 'user', null, published: false);
        $id = TwillHttpBasicAuth::query()->firstOrFail()->id;

        $this->assertTrue(PublishingRequirements::missing(['published' => true], $id));
        $this->assertFalse(PublishingRequirements::missing(['published' => true, 'password' => 'typed'], $id));
    }

    public function test_stored_credentials_that_cannot_be_read_count_as_missing(): void
    {
        $other = new \Illuminate\Encryption\Encrypter(random_bytes(32), 'aes-256-cbc');

        \DB::table('twill_basic_auth')->insert([
            'domain' => '*',
            'username' => $other->encryptString('user'),
            'password' => $other->encryptString('secret'),
            'published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = TwillHttpBasicAuth::query()->firstOrFail()->id;

        // the form sends nothing for them (they show empty): the row cannot be published as it is...
        $this->assertTrue(PublishingRequirements::missing(['published' => true], $id));
        $this->assertTrue(PublishingRequirements::missing(['published' => true, 'username' => 'new'], $id));
        // ...and can once both are typed again
        $this->assertFalse(
            PublishingRequirements::missing(['published' => true, 'username' => 'new', 'password' => 'new'], $id),
        );
    }

    public function test_the_message_is_in_the_language_of_the_admin_not_of_the_application(): void
    {
        $key = 'Domain cannot be enabled without a user name and a password.';

        app('translator')->addLines([$key => 'Message in French'], 'fr');
        app('translator')->addLines([$key => 'Message in Portuguese'], 'pt-BR');

        config(['app.locale' => 'pt-BR', 'twill.locale' => 'fr']);
        app()->setLocale('pt-BR');
        $this->assertSame('Message in French', PublishingRequirements::message());

        config(['twill.locale' => 'pt-BR']);
        app()->setLocale('fr');
        $this->assertSame('Message in Portuguese', PublishingRequirements::message());
    }
}
