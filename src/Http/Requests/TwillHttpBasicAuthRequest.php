<?php

namespace A17\TwillHttpBasicAuth\Http\Requests;

use Closure;
use Illuminate\Support\Facades\DB;
use A17\Twill\Http\Requests\Admin\Request;
use A17\TwillHttpBasicAuth\Support\CredentialResolver;
use A17\TwillHttpBasicAuth\Support\PublishingRequirements;

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
     * The password may be left empty on a save (the stored one is kept). A row without credentials may be saved, but not published.
     *
     * @return array<string, list<Closure|string>>
     */
    private function credentialRules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', $this->domainRule(...)],
            // a colon would split the user name in two in the Basic header: such a user name could never log in
            'username' => ['nullable', 'string', 'max:255', 'not_regex:/:/'],
            'password' => ['nullable', 'string', 'max:255'],
            'published' => [$this->credentialsToPublishRule(...)],
        ];
    }

    /**
     * The form is stopped earlier, by the controller, with a message of its own; this keeps the rule when the request is validated
     * by itself.
     */
    private function credentialsToPublishRule(string $attribute, mixed $value, Closure $fail): void
    {
        $current = $this->route('twillHttpBasicAuth');

        if (PublishingRequirements::missing($this->all(), is_scalar($current) ? $current : null)) {
            $fail(PublishingRequirements::message());
        }
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
