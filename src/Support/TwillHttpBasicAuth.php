<?php

namespace A17\TwillHttpBasicAuth\Support;

use Illuminate\Support\Facades\DB;

/**
 * What the admin screens ask about the package: where the credentials come from, which domain a URL has. What protects a
 * request lives in `CredentialResolver`, `Authenticator` and `Http\Middleware`.
 */
class TwillHttpBasicAuth
{
    public function config(string|null $key = null, mixed $default = null): mixed
    {
        return $key === null ? config('twill-http-basic-auth') : config("twill-http-basic-auth.{$key}", $default);
    }

    /**
     * Are the credentials in the .env? Then they win and the admin list is read-only.
     */
    public function hasDotEnv(): bool
    {
        return filled($this->config('keys.username')) || filled($this->config('keys.password'));
    }

    public function getDomain(string|null $url = null): string|null
    {
        $host = parse_url($url ?? request()->url(), PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    /**
     * One configuration for every domain: credentials in the .env, or a published `*` row.
     */
    public function allDomainsEnabled(): bool
    {
        return ($this->hasDotEnv() && CredentialResolver::enabled()) ||
            DB::table('twill_basic_auth')
                ->where('published', true)
                ->whereNull('deleted_at')
                ->where('domain', '*')
                ->exists();
    }
}
