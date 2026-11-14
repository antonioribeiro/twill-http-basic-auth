<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Models\TwillHttpBasicAuth;
use A17\TwillHttpBasicAuth\Repositories\TwillHttpBasicAuthRepository;

/**
 * What the admin list does with a row: stores it encrypted, never shows the password back, keeps the stored one when the form is
 * saved with the field empty, and writes the domain the way the middleware compares it.
 */
class AdminFormTest extends TestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function save(array $attributes): TwillHttpBasicAuth
    {
        $row = new TwillHttpBasicAuth();
        $row->fill($attributes)->save();

        return $row;
    }

    public function test_the_username_and_the_password_are_stored_encrypted(): void
    {
        $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'secret',
            'published' => true,
        ]);

        $stored = DB::table('twill_basic_auth')->first();

        $this->assertNotNull($stored);
        $this->assertIsString($stored->password);
        $this->assertIsString($stored->username);
        $this->assertNotSame('secret', $stored->password);
        $this->assertSame('secret', Crypt::decryptString($stored->password));
        $this->assertSame('user', Crypt::decryptString($stored->username));
    }

    public function test_the_form_never_gets_the_password_back(): void
    {
        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'secret',
            'published' => true,
        ]);

        $fields = app(TwillHttpBasicAuthRepository::class)->getFormFields($row);

        $this->assertStringNotContainsString('secret', (string) json_encode($fields));
        $this->assertSame('user', $fields['username'] ?? null);
    }

    public function test_the_form_does_not_show_the_password_of_the_env_either(): void
    {
        $this->envCredentials('envuser', 'envsecret');
        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'u',
            'password' => 'storedsecret',
            'published' => false,
        ]);

        $fields = app(TwillHttpBasicAuthRepository::class)->getFormFields($row);

        $this->assertStringNotContainsString('envsecret', (string) json_encode($fields));
        $this->assertStringNotContainsString('storedsecret', (string) json_encode($fields));
    }

    public function test_an_empty_password_on_save_keeps_the_stored_one(): void
    {
        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'secret',
            'published' => true,
        ]);

        $fields = app(TwillHttpBasicAuthRepository::class)->prepareFieldsBeforeSave($row, [
            'username' => 'user',
            'password' => '',
        ]);

        $this->assertArrayNotHasKey('password', $fields);
    }

    public function test_a_new_password_is_kept_when_given(): void
    {
        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'secret',
            'published' => true,
        ]);

        $fields = app(TwillHttpBasicAuthRepository::class)->prepareFieldsBeforeSave($row, ['password' => 'other']);

        $this->assertSame('other', $fields['password'] ?? null);
    }

    public function test_the_domain_is_written_the_way_the_middleware_compares_it(): void
    {
        foreach (
            [
                'HTTPS://Admin.Example.Test:8443/path/' => 'admin.example.test',
                'Staging.Example.Test.' => 'staging.example.test',
                'example.test' => 'example.test',
                '*' => '*',
            ]
            as $typed => $stored
        ) {
            $this->assertSame(
                $stored,
                $this->save(['domain' => $typed, 'published' => false])->getAttributes()['domain'],
            );
        }
    }

    public function test_a_row_without_a_password_is_never_published(): void
    {
        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => null,
            'published' => true,
        ]);

        $this->assertFalse((bool) DB::table('twill_basic_auth')->where('id', $row->getKey())->value('published'));
    }
}
