<?php

namespace A17\TwillHttpBasicAuth\Tests\Support;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A guard that knows one user, `staff@example.test` with the password `staffpw`, and never keeps a session.
 */
class FakeGuard implements Guard
{
    /** @var bool whether a visitor already is logged in (a session) */
    public static bool $loggedIn = false;

    /** @var array<string, mixed> what `validate()` was last asked */
    public static array $lastCredentials = [];

    public function check(): bool
    {
        return self::$loggedIn;
    }

    public function guest(): bool
    {
        return !self::$loggedIn;
    }

    public function user(): Authenticatable|null
    {
        return null;
    }

    public function id(): int|string|null
    {
        return null;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials = []): bool
    {
        self::$lastCredentials = $credentials;

        return ($credentials['email'] ?? null) === 'staff@example.test' &&
            ($credentials['password'] ?? null) === 'staffpw';
    }

    public function hasUser(): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): static
    {
        return $this;
    }
}
