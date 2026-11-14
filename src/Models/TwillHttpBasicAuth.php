<?php

namespace A17\TwillHttpBasicAuth\Models;

use A17\Twill\Models\Model;
use A17\TwillHttpBasicAuth\Models\Behaviors\Encrypt;
use Illuminate\Contracts\Encryption\DecryptException;
use A17\TwillHttpBasicAuth\Support\CredentialResolver;
use A17\TwillHttpBasicAuth\Support\Facades\TwillHttpBasicAuth as TwillHttpBasicAuthFacade;

/**
 * @property int $id
 * @property string|null $domain
 * @property string|null $username
 * @property string|null $password
 * @property bool|null $published
 * @property string $domain_string
 * @property string $status
 * @property string $from_dot_env
 * @property bool $configured
 * @property bool $allow_laravel_login
 * @property bool $allow_twill_login
 * @property string $allow_laravel_login_string
 * @property string $allow_twill_login_string
 */
class TwillHttpBasicAuth extends Model
{
    use Encrypt;

    protected $table = 'twill_basic_auth';

    protected $fillable = ['published', 'domain', 'username', 'password', 'allow_laravel_login', 'allow_twill_login'];

    protected $appends = [
        'domain_string',
        'status',
        'from_dot_env',
        'allow_laravel_login_string',
        'allow_twill_login_string',
    ];

    public function __toString()
    {
        return ''; // Bad fix for the blank() method that calls this recursively
    }

    public function getUsernameAttribute(): string|null
    {
        return $this->shown('username', 'keys.username');
    }

    public function setUsernameAttribute(string|null $value): void
    {
        $this->attributes['username'] = $this->encrypt($value);
    }

    /**
     * The password is never given back: not to the admin form, not to a page's JSON. It is written when typed, and an empty field keeps
     * what is stored (see the repository).
     */
    public function getPasswordAttribute(): string|null
    {
        return null;
    }

    /**
     * The decrypted password, for the admin form only. A value that cannot be decrypted is empty.
     */
    public function storedPassword(): string|null
    {
        $stored = $this->attributes['password'] ?? null;

        try {
            return $this->decrypt(is_string($stored) ? $stored : null);
        } catch (DecryptException) {
            return null;
        }
    }

    public function setDomainAttribute(string|null $value): void
    {
        $this->attributes['domain'] = CredentialResolver::normalizeDomain($value);
    }

    public function setPasswordAttribute(string|null $value): void
    {
        $this->attributes['password'] = $this->encrypt($value);
    }

    public function getPublishedAttribute(): bool
    {
        return (bool) ($this->attributes['published'] ?? false);
    }

    /**
     * What the admin shows for a credential: the one in the .env when there is one, else the stored one, decrypted. A stored value
     * that cannot be decrypted shows as empty (the row then counts as not configured), it is never shown as it is.
     */
    private function shown(string $column, string $environmentKey): string|null
    {
        if (TwillHttpBasicAuthFacade::hasDotEnv()) {
            $value = TwillHttpBasicAuthFacade::config($environmentKey);

            return is_string($value) ? $value : null;
        }

        $stored = $this->attributes[$column] ?? null;

        try {
            return $this->decrypt(is_string($stored) ? $stored : null);
        } catch (DecryptException) {
            return null;
        }
    }

    public function getDomainStringAttribute(): string|null
    {
        $domain = $this->domain;

        if ($domain === '*') {
            return '* (all domains)';
        }

        return $domain;
    }

    public function getConfiguredAttribute(): bool
    {
        if (TwillHttpBasicAuthFacade::hasDotEnv()) {
            return true;
        }

        return filled($this->attributes['username'] ?? null) && filled($this->attributes['password'] ?? null);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->configured) {
            return 'disabled';
        }

        if (!$this->published) {
            return 'not published';
        }

        return 'PROTECTED';
    }

    public function getFromDotEnvAttribute(): string
    {
        return TwillHttpBasicAuthFacade::hasDotEnv() ? 'YES' : 'no';
    }

    public function getAllowLaravelLoginStringAttribute(): string
    {
        return $this->allow_laravel_login ? 'Allowed' : 'Disallowed';
    }

    public function getAllowTwillLoginStringAttribute(): string
    {
        return $this->allow_twill_login ? 'Allowed' : 'Disallowed';
    }

    /**
     * A row is published only when it can protect something: a usable domain and credentials of its own. The .env credentials do
     * not count here: a row published on their strength would answer 503 on every host the day they are removed.
     */
    public function save(array $options = []): bool
    {
        $published = (bool) ($this->attributes['published'] ?? false);

        $this->attributes['published'] =
            $published &&
            filled($this->attributes['domain'] ?? null) &&
            filled($this->attributes['username'] ?? null) &&
            filled($this->attributes['password'] ?? null);

        return parent::save($options);
    }

    public function getCredentialsStringAttribute(): string
    {
        return $this->allow_twill_login ? 'Present' : 'Missing';
    }
}
