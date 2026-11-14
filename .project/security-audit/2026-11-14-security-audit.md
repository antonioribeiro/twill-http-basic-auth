# Security audit — area17/twill-http-basic-auth

- **Date:** 2026-11-14
- **Branch:** `3.x` (working tree, uncommitted changes included)
- **Auditor:** Claude Code (`security-audit` skill, standard pass)
- **Context assumed:** production (published package)
- **Scope:** `src/`, config, migrations, CI workflow, dependencies. Laravel + Twill only; frontend sections of the skill skipped.

## Method and limits

- Every file in `src/` was read in full.
- `composer audit`, `npm audit --omit=dev` and a git-history search for secrets were run.
- Twill's module authorization was read in `vendor/area17/twill/src/AuthServiceProvider.php` and `ModuleController.php`.
- Findings come from reading the code. No exploit tests were written and the test suite was not run.

## Summary

No critical or high findings. Two medium findings: the rate limit can be bypassed by rotating the Host header when protection is global, and any Twill PUBLISHER can read or change the stored password. The core (fail closed, constant-time comparison, no-store, global middleware) is solid.

**Score: 8/10.** Fixing M1 and M2 takes it to about 9.

## Status legend

- ✅ Done
- ❌ Not started
- 🚧 In progress
- ⏸️ Blocked

## Findings

### Critical

None.

### High

None.

### Medium

#### ✅ M1 — Throttle bypass by rotating the Host header

- **OWASP / CWE:** A07 / CWE-307
- **Where:** `src/Support/Throttle.php:45`, `src/Support/CredentialResolver.php:121-128`
- **What:** the counter key is `host + IP`. With `.env` credentials or a published `*` row, every host is protected, so each new Host name gets a fresh counter.
- **Condition:** the web server must answer arbitrary host names (default vhost, no `TrustHosts`).
- **Impact:** unlimited password guessing from one IP.
- **Fix:** key on what matched, not on what the client sent.
- **Done (2026-11-14):** `Credentials::$scope` (`*` for the `.env` and the star row, else the row's host); `Throttle` keys on scope + IP. Tests: `ThrottleTest::test_rotating_the_host_name_…` (two, `.env` and star row).

```php
// Throttle::key(): pass the matched row domain ('*' for .env or the star row)
return 'basic-auth:' . sha1($scope . '|' . $request->ip());
```

#### 🚧 M2 — Any Twill PUBLISHER can read, change or disable the protection

- **OWASP / CWE:** A01 / CWE-269, CWE-522
- **Where:** `src/Repositories/TwillHttpBasicAuthRepository.php:27-29`, `src/Http/Controllers/TwillHttpBasicAuthController.php:14`
- **What:** the module uses Twill's default gates (`edit`, `publish`, `delete` = PUBLISHER and ADMIN). The edit form sends the decrypted password to the browser. VIEWONLY can list and sees the username.
- **Impact:** a content editor can read the shared password, replace it, unpublish or delete the row.
- **Fix:** gate the module and stop echoing the password.

```php
// TwillHttpBasicAuthController
protected function setMiddlewarePermission()
{
    $this->middleware('can:manage-users'); // ADMIN and superadmin only
}
```

In `getFormFields()`, drop `storedPassword()` and show a "password is set" note instead. Empty-keeps-stored already works.

- **Done (2026-11-14):** the gate. The controller adds `can:manage-users` (test: `ModuleAccessTest`). A site test per role goes in the site's `BasicAuthAdminTest`.
- **Not done, on purpose (owner's ruling):** removing the password from the form. With the module for ADMIN only, whoever sees it already manages the site, and the owner wants to see and edit the password in the form. The model still never returns it in JSON or listings, and the `.env` password is never shown.

### Low / informational

| Status | # | Finding | OWASP / CWE | Where | Recommendation |
|---|---|---|---|---|---|
| ⏸️ | L1 | Passwords are encrypted, not hashed. A database leak plus `APP_KEY` gives plaintext. | A02 / CWE-257 | `src/Models/Behaviors/Encrypt.php:15` | Hash with `Hash::make` once M2 removes the need to show the password. |
|  |  | **Declined (owner and reviewer agree): needs a data migration (forbidden in this project: rows are fixed by hand), and hashing makes it impossible to show the password in the form, which the owner wants. The rows protect staging sites, not patient data. Encryption stays; the README says what it protects against.** |  |  |  |
| ✅ | L2 | Failed attempts and 429 lockouts are not logged. Only misconfiguration is. | A09 / CWE-778 | `src/Http/Middleware.php:44-58` | Log host and IP on failure and on lockout. Never log the credentials. |
|  |  | **Done: `Middleware` logs one warning per failure and one when the limit is reached, host and address only. Test: `ThrottleTest::test_a_failed_attempt_and_the_lockout_are_logged_without_the_credentials`.** |  |  |  |
| ✅ | L3 | No minimum password strength; one character is accepted. A username containing `:` saves but can never log in. | A07 / CWE-521 | `src/Http/Requests/TwillHttpBasicAuthRequest.php:37-38` | Add `min:12` to the password and `not_regex:/:/` to the username. |
|  |  | **Done for the user name: `not_regex:/:/` (test in `AdminFormTest`). The `min:12` for the password is not applied: re-saving a row with an older short password from the loaded form would be refused, and these are shared staging passwords.** |  |  |  |
| ✅ | L4 | Database login makes the Basic prompt a second place to guess Twill/Laravel account passwords, limited only by this throttle (weakened by M1). The `laravel` guard has no conditions, so open registration means anyone passes. | A07 / CWE-307 | `src/Support/Authenticator.php:44-54`, `src/config/twill-http-basic-auth.php:56` | Fix M1. Document the open-registration risk; suggest a condition such as a verified or active flag. |
|  |  | **Documented in the README (Security, "What it cannot do for you"), with the `conditions` advice. M1 is fixed, which strengthens the throttle.** |  |  |  |
| ✅ | L5 | With `ENABLED=false` and `.env` keys set, the admin shows the `.env` username, "From .env: YES" and a disabled form, but the row's own credentials are in force. | A04 | `src/Models/TwillHttpBasicAuth.php:101`, `:129` | Make the admin use the same test as the resolver: enabled **and** keys set. |
|  |  | **Done: `TwillHttpBasicAuth::hasDotEnv()` is true only when the package is enabled **and** a key is set, the same test as the middleware. Tests in `AdminFormTest`.** |  |  |  |
| ⏸️ | L6 | The admin index creates rows on a GET, using the request host. | A04 | `src/Http/Controllers/TwillHttpBasicAuthController.php:39-72` | Seed from `app.url` only, or move to a migration or install command. |
|  |  | **Deferred (owner agrees): low risk (rows start unpublished and the first admin to open the list creates them); an install command would change how the package is set up. Revisit with the 3.0 release.** |  |  |  |
| ✅ | L7 | CI actions are pinned by tag, not commit SHA, and there is no `permissions:` block. | A08 | `.github/workflows/tests.yml:37-39` | Pin by SHA and add `permissions: contents: read`. |
|  |  | **Done: actions pinned by commit SHA (version in a comment), `permissions: contents: read`. Dependabot or Renovate can keep the SHAs current.** |  |  |  |

## Dependencies and secrets

- `composer audit`: no advisories. Four abandoned packages, all pulled in by Twill: `league/flysystem-azure-blob-storage`, `matthewbdaly/laravel-azure-storage`, `microsoft/azure-storage-blob`, `microsoft/azure-storage-common`.
- `npm audit --omit=dev`: 0 vulnerabilities.
- No `.env`, `.key`, `.pem` or `.secret` files tracked or in git history.

## Already done well

- **Fails closed** (A04): 503 on unusable credentials, never 200.
- **Constant-time comparison** (A02): `hash_equals` on digests, both fields always compared.
- **Not cacheable** (A05): `Cache-Control: no-store, private` on every 401, 429, 503 and protected response.
- **Closed by default** (A01): global middleware, empty `ignore.paths`, no default credentials.
- **No injection surface** (A03): query builder only, no raw Blade output.
- **No password leakage** in JSON, listings or revisions.
- **Per-request resolution**: safe under Octane.
- **Documented limits**: the host-name and trusted-proxy caveats are in the README.

## Not applicable

Paywall/billing, WebSocket, CORS, SSRF, JWT, Docker, robots.txt, frontend integrity checks.

## Next steps

1. ✅ M1 — change the throttle key, add a test for Host rotation on a `*` row and on `.env` credentials. Small.
2. 🚧 M2 — gate done; password still shown to ADMIN (ruling); site test per role pending.
3. ✅ L2, L3, L5 — logging, validation rules, admin display. Small each.
4. ⏸️ L1 — declined (see the table).
5. ✅ L4, L7; ⏸️ L6 deferred.

## Disclaimer

This report was produced by an automated tool (Claude Code). It is an aid, not a certified professional audit. Findings must be reviewed and validated by a developer or security expert before any corrective action. Some may be false positives or may not apply to your context.
