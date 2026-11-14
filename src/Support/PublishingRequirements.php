<?php

namespace A17\TwillHttpBasicAuth\Support;

use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

/**
 * A domain that is published protects its host. Without a user name and a password nobody could open it (or it would close the host
 * for everybody), so it cannot be published until both are set: typed now, or already stored for the row AND readable (a value
 * written with another APP_KEY is stored but cannot be used, and the admin shows it empty).
 */
class PublishingRequirements
{
    /**
     * The message the admin shows, in the language of the admin (`twill.locale`, the logged-in user's choice), not of the site. It is
     * an English key: translate it in the application's `lang/*.json`.
     */
    public static function message(): string
    {
        $locale = config('twill.locale', config('twill.fallback_locale', 'en'));

        return trans(
            'Domain cannot be enabled without a user name and a password.',
            [],
            is_string($locale) ? $locale : 'en',
        );
    }

    /**
     * True when this save would publish a row that has no user name or no password.
     *
     * @param array<mixed> $input what the form sends
     * @param int|string|null $id the row being saved, null for a new one
     */
    public static function missing(array $input, int|string|null $id): bool
    {
        if (!filter_var($input['published'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $stored =
            $id === null
                ? null
                : DB::table('twill_basic_auth')
                    ->where('id', $id)
                    ->first(['username', 'password']);

        return !(
            (filled($input['username'] ?? null) || self::readable($stored->username ?? null)) &&
            (filled($input['password'] ?? null) || self::readable($stored->password ?? null))
        );
    }

    private static function readable(mixed $stored): bool
    {
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        try {
            return Crypt::decryptString($stored) !== '';
        } catch (Throwable) {
            return false;
        }
    }
}
