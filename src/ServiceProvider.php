<?php

namespace A17\TwillHttpBasicAuth;

use Illuminate\Support\Str;
use A17\Twill\Facades\TwillCapsules;
use Illuminate\Contracts\Http\Kernel;
use A17\Twill\TwillPackageServiceProvider;
use A17\TwillHttpBasicAuth\Support\TwillHttpBasicAuth;

class ServiceProvider extends TwillPackageServiceProvider
{
    /** @var bool */
    protected $autoRegisterCapsules = false;

    public function boot(): void
    {
        $this->registerThisCapsule();

        $this->registerViews();

        $this->registerConfig();

        $this->configureMiddleware();

        parent::boot();
    }

    protected function registerThisCapsule(): void
    {
        $namespace = $this->getCapsuleNamespace();

        TwillCapsules::registerPackageCapsule(
            Str::afterLast($namespace, '\\'),
            $namespace,
            $this->getPackageDirectory() . '/src',
        );

        $this->app->singleton(TwillHttpBasicAuth::class);
    }

    public function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'twill-http-basic-auth');
    }

    public function registerConfig(): void
    {
        $package = 'twill-http-basic-auth';

        $path = __DIR__ . "/config/{$package}.php";

        $this->mergeConfigFrom($path, $package);

        $this->publishes([
            $path => config_path("{$package}.php"),
        ]);
    }

    /**
     * The middleware is global: it sees every request, whatever its route is. A middleware group would leave out what Laravel serves
     * outside the groups (image routes, robots.txt, health checks, a dashboard like Horizon, pages that do not exist, the automatic
     * OPTIONS answers). It is pushed after the application's own global middleware, so the proxies are trusted (and the host is
     * known) before it decides.
     */
    public function configureMiddleware(): void
    {
        if (config('twill-http-basic-auth.middleware.automatic') !== true) {
            return;
        }

        $middleware = config('twill-http-basic-auth.middleware.class');

        if (is_string($middleware)) {
            $this->app->make(Kernel::class)->pushMiddleware($middleware);
        }
    }
}
