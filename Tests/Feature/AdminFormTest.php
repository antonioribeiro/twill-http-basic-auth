<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Models\TwillHttpBasicAuth;
use A17\TwillHttpBasicAuth\Http\Requests\TwillHttpBasicAuthRequest;
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

    public function test_the_form_loads_the_stored_password_and_the_username(): void
    {
        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'secret',
            'published' => true,
        ]);

        $fields = app(TwillHttpBasicAuthRepository::class)->getFormFields($row);

        $this->assertSame('user', $fields['username'] ?? null);
        $this->assertSame('secret', $fields['password'] ?? null);
    }

    public function test_saving_the_form_as_loaded_keeps_the_password_working(): void
    {
        $repository = app(TwillHttpBasicAuthRepository::class);

        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'secret',
            'published' => true,
        ]);

        $fields = $repository->getFormFields($row);
        $fields['username'] = 'renamed';

        $repository->update(TwillHttpBasicAuth::query()->firstOrFail()->id, $fields);

        $stored = TwillHttpBasicAuth::query()->firstOrFail();

        $this->assertSame('secret', $stored->storedPassword());
        $this->assertSame('renamed', $stored->username);
        $this->assertTrue($stored->published);
    }

    public function test_a_password_typed_in_the_form_is_saved(): void
    {
        $repository = app(TwillHttpBasicAuthRepository::class);

        $this->save(['domain' => 'admin.example.test', 'username' => 'user', 'published' => false]);

        $repository->update(TwillHttpBasicAuth::query()->firstOrFail()->id, [
            'domain' => 'admin.example.test',
            'username' => 'user',
            'password' => 'typed',
        ]);

        $this->assertSame('typed', TwillHttpBasicAuth::query()->firstOrFail()->storedPassword());
    }

    public function test_the_form_does_not_show_the_password_of_the_env(): void
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

    public function test_each_login_switch_has_a_short_note_naming_the_accounts_email_and_password(): void
    {
        $source = (string) file_get_contents(view()->getFinder()->find('twill-http-basic-auth::admin.form'));

        $blocks = array_slice(explode("@formField('checkbox'", $source), 1);

        foreach (['allow_twill_login' => 'Twill', 'allow_laravel_login' => 'website'] as $field => $account) {
            $block = collect($blocks)->first(fn(string $block): bool => str_contains($block, "'{$field}'"));

            $this->assertIsString($block, "the {$field} checkbox is missing");

            preg_match("/'note' => '([^']*)'/", $block, $note);

            $this->assertStringContainsString("{$account} e-mail and password", $note[1] ?? '', "{$field} has no note");
            $this->assertLessThan(70, strlen($note[1] ?? ''), "the note of {$field} is too long");
        }
    }

    public function test_with_the_package_disabled_the_admin_shows_the_row_not_the_env(): void
    {
        config([
            'twill-http-basic-auth.enabled' => false,
            'twill-http-basic-auth.keys.username' => 'envuser',
            'twill-http-basic-auth.keys.password' => 'envsecret',
        ]);

        $row = $this->save([
            'domain' => 'admin.example.test',
            'username' => 'rowuser',
            'password' => 'rowsecret',
            'published' => true,
        ]);

        $this->assertSame('rowuser', $row->username);
        $this->assertSame('no', $row->from_dot_env);
        $this->assertSame(
            'rowsecret',
            app(TwillHttpBasicAuthRepository::class)->getFormFields($row)['password'] ?? null,
        );
    }

    public function test_with_the_package_enabled_and_the_keys_set_the_admin_shows_the_env(): void
    {
        $this->envCredentials('envuser', 'envsecret');

        $row = $this->save(['domain' => 'admin.example.test', 'username' => 'rowuser', 'password' => 'rowsecret']);

        $this->assertSame('envuser', $row->username);
        $this->assertSame('YES', $row->from_dot_env);
    }

    public function test_a_username_with_a_colon_is_refused_because_it_could_never_log_in(): void
    {
        $rules = (new TwillHttpBasicAuthRequest())->rulesForCreate();
        $data = ['domain' => 'admin.example.test', 'username' => 'a:b', 'password' => 'secret'];

        $this->assertTrue(Validator::make($data, $rules)->errors()->has('username'));
        $this->assertFalse(
            Validator::make(['username' => 'ab'] + $data, $rules)
                ->errors()
                ->has('username'),
        );
    }

    public function test_a_row_whose_credentials_cannot_be_decrypted_is_never_published(): void
    {
        $other = new \Illuminate\Encryption\Encrypter(random_bytes(32), 'aes-256-cbc');

        $row = new TwillHttpBasicAuth();
        $row->forceFill(['domain' => '*', 'published' => true]);
        $row->setRawAttributes([
            'domain' => '*',
            'published' => true,
            'username' => $other->encryptString('u'),
            'password' => $other->encryptString('p'),
        ]);
        $row->save();

        $this->assertFalse((bool) DB::table('twill_basic_auth')->where('id', $row->getKey())->value('published'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function errorsOf(array $data): \Illuminate\Support\MessageBag
    {
        $request = TwillHttpBasicAuthRequest::create('/', 'POST', $data);

        return Validator::make($request->all(), $request->rulesForCreate())->errors();
    }

    public function test_publishing_a_row_without_a_user_name_or_a_password_is_refused_with_a_message(): void
    {
        $data = ['domain' => 'admin.example.test', 'published' => true];

        $this->assertTrue($this->errorsOf($data + ['username' => 'user'])->has('published'), 'no password');
        $this->assertTrue($this->errorsOf($data + ['password' => 'secret'])->has('published'), 'no user name');
        $this->assertFalse($this->errorsOf($data + ['username' => 'user', 'password' => 'secret'])->has('published'));
        $this->assertFalse(
            $this->errorsOf(['published' => false] + $data)->has('published'),
            'a row that is not published may be incomplete',
        );
    }
}
