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
     * Are the credentials of the .env in force? Only when the package is enabled and a key is set (the same test the middleware makes):
     * then they win and the admin list is read-only. With the package disabled the rows of the list decide.
     */
    public function hasDotEnv(): bool
    {
        return CredentialResolver::enabled() &&
            (filled($this->config('keys.username')) || filled($this->config('keys.password')));
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
        return $this->hasDotEnv() ||
            DB::table('twill_basic_auth')
                ->where('published', true)
                ->whereNull('deleted_at')
                ->where('domain', '*')
                ->exists();
    }
}
