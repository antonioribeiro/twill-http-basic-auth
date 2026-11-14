<?php

namespace A17\TwillHttpBasicAuth\Support;

use stdClass;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use A17\TwillHttpBasicAuth\Exceptions\MisconfiguredProtection;

/**
 * Which credentials, if any, protect this request.
 *
 *  - Credentials in the .env (`keys.username`, `keys.password`) protect every host, when the package is enabled.
 *  - Otherwise a published row of the admin list: the `*` row for every host, else the row of the request's own host.
 *  - No credentials and no row: the host is open (that is how the naked domain stays public while the admin host is closed).
 *
 * A protected host whose credentials cannot be used throws `MisconfiguredProtection`: it is never treated as open.
 * The answer is kept on the request, so a long-running worker (Octane) never carries it over to another request.
 */
class CredentialResolver
{
    private const ATTRIBUTE = 'twill-http-basic-auth.credentials';

    public function __construct(private readonly TwillHttpBasicAuth $settings) {}

    /**
     * @throws MisconfiguredProtection
     */
    public function for(Request $request): Credentials|null
    {
        $known = $request->attributes->get(self::ATTRIBUTE);

        if ($known instanceof Credentials) {
            return $known;
        }

        if ($known === false) {
            return null;
        }

        // the .env credentials win when the package is enabled; otherwise (disabled, or no keys) the rows of the admin list decide
        $credentials = $this->environmentIsActive() ? $this->fromEnvironment() : $this->fromDatabase($request);

        $request->attributes->set(self::ATTRIBUTE, $credentials ?? false);

        return $credentials;
    }

    /**
     * What an admin typed as a domain, as a host: no scheme, port, path or trailing dot, lower case. `*` is every host. Null when
     * nothing usable is left (`http://`, an empty string).
     */
    public static function normalizeDomain(string|null $value): string|null
    {
        $domain = trim((string) $value);

        if ($domain === '') {
            return null;
        }

        if (str_contains($domain, '://')) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }

        $domain = (string) preg_replace('~[/:?#].*$~', '', $domain);

        return $domain === '' ? null : self::normalizeHost($domain);
    }

    /**
     * The host as the rows are compared: lower case, no port, no trailing dot.
     */
    public static function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host), '.'));
    }

    /**
     * `ENABLED` is read the way a .env flag is written: 1, true, yes and on are on; anything else is off.
     */
    public static function enabled(): bool
    {
        return filter_var(config('twill-http-basic-auth.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    private function environmentIsActive(): bool
    {
        return $this->settings->hasDotEnv();
    }

    private function fromEnvironment(): Credentials
    {
        $username = config('twill-http-basic-auth.keys.username');
        $password = config('twill-http-basic-auth.keys.password');

        if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
            throw new MisconfiguredProtection('The credentials of the .env are only half set.');
        }

        return new Credentials(
            $username,
            $password,
            '*',
            $this->guardsFor(fn(string $name): bool => $this->envGuardEnabled($name)),
        );
    }

    private function fromDatabase(Request $request): Credentials|null
    {
        $host = self::normalizeHost($request->getHost());
        $star = null;
        $exact = null;

        foreach (
            DB::table('twill_basic_auth')->where('published', true)->whereNull('deleted_at')->orderBy('id')->get()
            as $row
        ) {
            $domain = self::normalizeHost(is_string($row->domain ?? null) ? $row->domain : '');

            if ($domain === '*') {
                $star ??= $row;
            } elseif ($domain !== '' && $domain === $host) {
                $exact ??= $row;
            }
        }

        $row = $star ?? $exact;

        if (!($row instanceof stdClass)) {
            return null;
        }

        return new Credentials(
            $this->decrypt($row->username ?? null, 'username', $host),
            $this->decrypt($row->password ?? null, 'password', $host),
            $row === $star ? '*' : $host,
            $this->guardsFor(fn(string $name): bool => (bool) ($row->{"allow_{$name}_login"} ?? false)),
        );
    }

    private function decrypt(mixed $value, string $field, string $host): string
    {
        if (!is_string($value) || $value === '') {
            throw new MisconfiguredProtection("The {$field} of the row for {$host} is empty.");
        }

        try {
            $plain = Crypt::decryptString($value);
        } catch (Throwable) {
            throw new MisconfiguredProtection("The {$field} of the row for {$host} cannot be decrypted.");
        }

        if ($plain === '') {
            throw new MisconfiguredProtection("The {$field} of the row for {$host} is empty.");
        }

        return $plain;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function stringKeys(array $values): array
    {
        return array_filter($values, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function envGuardEnabled(string $name): bool
    {
        return config("twill-http-basic-auth.database-login.{$name}.enabled") === true;
    }

    /**
     * @param callable(string): bool $enabled whether the login of the application named `twill` or `laravel` is allowed here
     * @return list<array{guard: string, column: string, conditions: array<string, mixed>}>
     */
    private function guardsFor(callable $enabled): array
    {
        $guards = [];
        $configured = config('twill-http-basic-auth.database-login');

        foreach (is_array($configured) ? $configured : [] as $name => $definition) {
            if (!is_string($name) || !is_array($definition) || !$enabled($name)) {
                continue;
            }

            $guard = $definition['guard'] ?? null;
            $column = $definition['username-column'] ?? 'email';

            $conditions = is_array($definition['conditions'] ?? null) ? $definition['conditions'] : [];

            if (is_string($guard) && is_string($column)) {
                $guards[] = ['guard' => $guard, 'column' => $column, 'conditions' => $this->stringKeys($conditions)];
            }
        }

        return $guards;
    }
}
