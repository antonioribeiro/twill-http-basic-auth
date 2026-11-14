<?php

namespace A17\TwillHttpBasicAuth\Http\Requests;

use Closure;
use Illuminate\Support\Facades\DB;
use A17\Twill\Http\Requests\Admin\Request;
use A17\TwillHttpBasicAuth\Support\CredentialResolver;

class TwillHttpBasicAuthRequest extends Request
{
    /**
     * @return array<string, list<Closure|string>>
     */
    public function rulesForCreate(): array
    {
        return $this->credentialRules();
    }

    /**
     * @return array<string, list<Closure|string>>
     */
    public function rulesForUpdate(): array
    {
        return $this->credentialRules();
    }

    /**
     * The password may be left empty on a save (the stored one is kept); a row without credentials simply stays unpublished.
     *
     * @return array<string, list<Closure|string>>
     */
    private function credentialRules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', $this->domainRule(...)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A domain is a host name or `*`, once. What is typed is read the way it will be stored (scheme, port, path and capitals
     * removed), so `HTTPS://Admin.Example.Test:8443/` and `admin.example.test` are the same domain.
     */
    private function domainRule(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = CredentialResolver::normalizeDomain(is_string($value) ? $value : null);

        $hostname = '/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/';

        if ($domain === null || ($domain !== '*' && preg_match($hostname, $domain) !== 1)) {
            $fail('The domain must be a host name such as admin.example.com, or * for every domain.');

            return;
        }

        $taken = DB::table('twill_basic_auth')->where('domain', $domain)->whereNull('deleted_at');

        $current = $this->route('twillHttpBasicAuth');

        if (is_scalar($current)) {
            $taken->where('id', '!=', $current);
        }

        if ($taken->exists()) {
            $fail('There already is a row for this domain.');
        }
    }
}
