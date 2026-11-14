# Changelog

## Unreleased (3.0)

3.x is a major release: PHP 8.3+, Laravel 12 or 13, Twill 3.5 or 3.6.

- **Tooling:** same quality setup as `pragmarx/twill-ai-translation`: PHPUnit that fails on deprecations, warnings, risky and skipped tests, Orchestra Testbench, PHPStan level max on `src` and `Tests`, PHP CS Fixer, Prettier, husky + lint-staged, one CI workflow (PHP x Laravel x Twill, lowest dependencies, quality).

### Security fixes (what 2.x did wrong)

- **A protected host could be open.** A row with an empty or NULL password, an undecryptable value, or half of the `.env` pair left the host unprotected. It now answers 503.
- **Only the `web` group was protected.** The API, the admin on its own middleware group (the usual case), the image routes, `robots.txt`, a Horizon dashboard and every unrouted page were open on a "protected" host. The middleware is global now.
- **The rate limiter counted every request, successful ones too, under one key shared by all visitors,** so anyone could lock everybody out and a busy user got a 429. It counts failures only (and not requests with no credentials), per IP address and host, and clears on a correct login.
- **Credentials were compared with `===`** and the library shipped default credentials (`usern@me` / `passw0rt`). Both are gone.
- **A value that could not be decrypted was used as it was** (and a failed encryption stored the text `ENCRYPTION ERROR`). Decryption is strict now.
- **The admin form printed the stored password** (and the `.env` one), and Twill's revisions kept every typed password as JSON. Neither happens now; revisions are off for this module.
- **A library path default left `/admin/*` and `/api/v1/*` open** on every protected host. No path is ignored by default.
- **State was kept in a singleton**: under Octane, the first request's row served the next ones. Resolution is per request.
- **No `Cache-Control`** on the 401 or on the pages behind it.
- **Rows moved to the trash kept protecting**, a row with an unusable domain (`*.example.com`, `http://`) showed as `PROTECTED` and protected nothing, and a row published on the strength of the `.env` credentials caused a 503 on every host the day those were removed. Rows are now saved unpublished unless they have a usable domain and credentials of their own; the domain is validated (a host name or `*`, once).
- **`ENABLED=1` (or `yes`) answered 500 on every request** and `ENABLED` unset ignored the rows. The flag is read leniently and only gates the `.env` credentials.
- **The columns `username` and `password` were `varchar(255)`**, too short for an encrypted value of 32 characters or more (a 500 on PostgreSQL and strict MySQL, a silent cut on lenient MySQL). A migration makes them `text`.
- **The two login switches of the admin form have a short note:** people use their own Twill or website e-mail and password at the prompt.
- **Twill accounts that were not published could pass** with their own password; the Twill login now asks for `published`.

### Upgrading from 2.x

1. PHP 8.3+, Laravel 12 or 13, Twill 3.5 or 3.6.
2. `composer remove area17/http-basic-auth` (its code is inside the package now) and drop its `repositories` entry. Anything that used `A17\HttpBasicAuth\*` or the `http-basic-auth` middleware alias: use `A17\TwillHttpBasicAuth\Http\Middleware`.
3. `php artisan migrate` (the credential columns become `text`).
4. A published `config/twill-http-basic-auth.php` of 2.x: delete the `middleware.groups` entry (it is ignored now: the middleware is global) and rename `rate-limiting.attemps-per-minute` to `attempts-per-minute`. Better: publish the new file again and re-apply your values.
5. Rows saved with a plain-text username or password (by hand, or by an old version) now answer 503, and so does the admin of that host, so they cannot be fixed there. Fix them where you can still log in, or in `php artisan tinker`: `DB::table('twill_basic_auth')->update(['published' => false])`, then save each password again in the admin. If you are locked out, `TWILL_HTTP_BASIC_AUTH_ENABLED=false` does not help (rows are not gated by it): unpublish the row in the database.
6. The revisions table `twill_basic_auth_revisions` may hold typed passwords in clear text: `DB::table('twill_basic_auth_revisions')->delete()` in tinker.
7. The rate limit is 10 failed attempts a minute (it was 500 requests); `TWILL_HTTP_BASIC_AUTH_RATE_LIMITING_ATTEMPTS` still sets it.
8. If you relied on `/admin/*` or `/api/v1/*` being open, list the paths you want open in `ignore.paths`.
