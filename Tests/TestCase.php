<?php

namespace A17\TwillHttpBasicAuth\Tests;

use Illuminate\Support\Facades\DB;
use A17\Twill\TwillServiceProvider;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use A17\TwillHttpBasicAuth\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A bare Laravel application (Orchestra Testbench) with Twill and this package, on an in-memory database. It has no host
 * application: whatever the package needs from one must be provided here, or the package is not standalone.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @param Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TwillServiceProvider::class, ServiceProvider::class];
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array');

        // the host application's groups: `api`, and the one Twill's admin runs on (`twill.admin_middleware_group`)
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        // like the site's own `admin` group: one of Laravel's sorted middleware comes first, ours is appended after it
        $kernel->setMiddlewareGroups(
            array_merge($kernel->getMiddlewareGroups(), [
                'api' => [],
                'admin' => [\Illuminate\Routing\Middleware\SubstituteBindings::class],
            ]),
        );
        $app['config']->set('twill.admin_middleware_group', 'admin');

        // nothing is protected until a test says so
        $app['config']->set('twill-http-basic-auth.enabled', false);
        $app['config']->set('twill-http-basic-auth.keys.username', null);
        $app['config']->set('twill-http-basic-auth.keys.password', null);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/', fn(): string => 'ok');
            Route::get('/probe', fn(): string => 'probe');
            Route::post('/probe', fn(): string => 'posted');
        });

        // no middleware group at all: what Laravel serves outside `web` and `api` (image routes, robots.txt, health checks, dashboards)
        Route::get('/ungrouped', fn(): string => 'ungrouped');

        Route::middleware('api')->get('/api/ping', fn(): string => 'pong');
        Route::middleware('admin')->get('/admin-area', fn(): string => 'admin');
        Route::middleware(['admin', \A17\TwillHttpBasicAuth\Tests\Support\RedirectingAuthenticate::class])->get(
            '/admin-private',
            fn(): string => 'private',
        );
    }

    /**
     * A row of the admin's Basic Auth list, stored the way the admin stores it: username and password encrypted.
     */
    protected function row(
        string $domain,
        string|null $username = 'user',
        string|null $password = 'secret',
        bool $published = true,
    ): void {
        DB::table('twill_basic_auth')->insert([
            'domain' => $domain,
            'username' => $username === null ? null : Crypt::encryptString($username),
            'password' => $password === null ? null : Crypt::encryptString($password),
            'published' => $published,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function enable(): void
    {
        config(['twill-http-basic-auth.enabled' => true]);
    }

    /**
     * Credentials from the .env: they protect every host.
     */
    protected function envCredentials(string $username = 'envuser', string $password = 'envsecret'): void
    {
        config([
            'twill-http-basic-auth.enabled' => true,
            'twill-http-basic-auth.keys.username' => $username,
            'twill-http-basic-auth.keys.password' => $password,
        ]);
    }

    /**
     * A request to `$path` on `$host`, with Basic credentials when given.
     *
     * @param array<string, string> $headers
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function visit(
        string $host,
        string|null $username = null,
        string|null $password = null,
        string $path = '/',
        array $headers = [],
        string $method = 'GET',
        string $ip = '127.0.0.1',
    ): TestResponse {
        $server = ['HTTP_HOST' => $host, 'REMOTE_ADDR' => $ip];

        if ($username !== null) {
            $server['PHP_AUTH_USER'] = $username;
            $server['PHP_AUTH_PW'] = $password ?? '';
        }

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, "http://{$host}{$path}", [], [], [], $server);
    }
}
