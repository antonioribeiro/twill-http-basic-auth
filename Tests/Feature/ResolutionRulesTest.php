<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Models\TwillHttpBasicAuth;
use A17\TwillHttpBasicAuth\Support\TwillHttpBasicAuth as Settings;

/**
 * Edge cases of which credentials apply.
 */
class ResolutionRulesTest extends TestCase
{
    public function test_a_row_that_was_moved_to_the_trash_protects_nothing(): void
    {
        $this->row('admin.example.test');
        DB::table('twill_basic_auth')->update(['deleted_at' => now()]);

        $this->visit('admin.example.test')->assertOk();
        $this->assertFalse(app(Settings::class)->allDomainsEnabled());
    }

    public function test_a_trashed_star_row_does_not_count_as_every_domain(): void
    {
        $this->row('*');
        DB::table('twill_basic_auth')->update(['deleted_at' => now()]);

        $this->assertFalse(app(Settings::class)->allDomainsEnabled());
    }

    public function test_env_keys_with_the_package_disabled_leave_the_rows_in_charge(): void
    {
        $this->row('admin.example.test', 'rowuser', 'rowsecret');
        config([
            'twill-http-basic-auth.enabled' => false,
            'twill-http-basic-auth.keys.username' => 'envuser',
            'twill-http-basic-auth.keys.password' => 'envsecret',
        ]);

        $this->visit('admin.example.test', 'rowuser', 'rowsecret')->assertOk();
        $this->visit('admin.example.test')->assertStatus(401);
        $this->visit('example.test')->assertOk();
    }

    public function test_the_enabled_flag_is_read_leniently(): void
    {
        foreach (['1', 'true', 'yes', 'on', 1, true] as $value) {
            config([
                'twill-http-basic-auth.enabled' => $value,
                'twill-http-basic-auth.keys.username' => 'envuser',
                'twill-http-basic-auth.keys.password' => 'envsecret',
            ]);

            $this->visit('example.test')->assertStatus(401);
        }

        foreach (['0', 'false', 'no', 'off', '', 0, false, null] as $value) {
            config(['twill-http-basic-auth.enabled' => $value]);

            $this->visit('example.test')->assertOk();
        }
    }

    public function test_enabled_with_no_keys_leaves_the_rows_in_charge(): void
    {
        $this->row('admin.example.test');
        config(['twill-http-basic-auth.enabled' => true]);

        $this->visit('admin.example.test')->assertStatus(401);
        $this->visit('example.test')->assertOk();
    }

    public function test_with_two_rows_for_one_host_the_first_one_decides(): void
    {
        $this->row('admin.example.test', 'first', 'firstsecret');
        $this->row('admin.example.test', 'second', 'secondsecret');

        $this->visit('admin.example.test', 'first', 'firstsecret')->assertOk();
        $this->visit('admin.example.test', 'second', 'secondsecret')->assertStatus(401);
    }

    public function test_with_env_credentials_a_row_without_credentials_of_its_own_is_still_saved_unpublished(): void
    {
        config([
            'twill-http-basic-auth.enabled' => true,
            'twill-http-basic-auth.keys.username' => 'envuser',
            'twill-http-basic-auth.keys.password' => 'envsecret',
        ]);

        $row = new TwillHttpBasicAuth();
        $row->fill(['domain' => '*', 'published' => true])->save();

        // removing the env keys at go-live must not leave a published row with nothing in it (every host would answer 503)
        $this->assertFalse((bool) DB::table('twill_basic_auth')->where('id', $row->getKey())->value('published'));
    }

    public function test_a_row_with_no_usable_domain_is_saved_unpublished(): void
    {
        $row = new TwillHttpBasicAuth();
        $row->fill(['domain' => 'http://', 'username' => 'u', 'password' => 'p', 'published' => true])->save();

        $this->assertFalse((bool) DB::table('twill_basic_auth')->where('id', $row->getKey())->value('published'));
    }

    public function test_saving_without_saying_whether_it_is_published_does_not_fail(): void
    {
        $row = new TwillHttpBasicAuth();
        $row->fill(['domain' => 'admin.example.test', 'username' => 'u', 'password' => 'p'])->save();

        $this->assertFalse((bool) DB::table('twill_basic_auth')->where('id', $row->getKey())->value('published'));
    }

    public function test_only_the_exact_host_of_a_row_is_protected(): void
    {
        $this->row('admin.example.test');

        // a row protects a host NAME: whole-site protection is the `*` row or the .env credentials
        $this->visit('www.admin.example.test')->assertOk();
        $this->visit('other.example.test')->assertOk();
    }
}
