<?php

namespace A17\TwillHttpBasicAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use A17\Twill\Services\Listings\Columns\Text;
use A17\Twill\Services\Listings\TableColumns;
use Illuminate\Contracts\Foundation\Application;
use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Http\Controllers\Admin\ModuleController;
use A17\TwillHttpBasicAuth\Support\PublishingRequirements;
use A17\TwillHttpBasicAuth\Repositories\TwillHttpBasicAuthRepository;
use A17\TwillHttpBasicAuth\Support\Facades\TwillHttpBasicAuth as TwillHttpBasicAuthFacade;

class TwillHttpBasicAuthController extends ModuleController
{
    protected $moduleName = 'twillHttpBasicAuths';

    protected $titleColumnKey = 'domain_string';

    protected $titleFormKey = 'domain';

    /** @var array<string, string> */
    protected $defaultOrders = ['domain' => 'asc'];

    /**
     * The module holds the shared password: only who manages users (ADMIN and superadmin) reaches it, whatever Twill's own module
     * gates (`list`, `edit`, `publish`, `delete`: PUBLISHER and ADMIN) would say.
     */
    public function __construct(Application $app, Request $request)
    {
        parent::__construct($app, $request);

        $this->middleware('can:manage-users');
    }

    public function index(int|null $parentModuleId = null): mixed
    {
        $this->generateDomains();

        $this->setIndexOptions();

        return parent::index($parentModuleId = null);
    }

    protected function getViewPrefix(): string|null
    {
        return 'twill-http-basic-auth::admin';
    }

    public function generateDomains(): void
    {
        if (DB::table('twill_basic_auth')->count() !== 0) {
            return;
        }

        $appUrl = config('app.url');

        $appDomain = TwillHttpBasicAuthFacade::getDomain(is_string($appUrl) ? $appUrl : null);

        $currentDomain = TwillHttpBasicAuthFacade::getDomain(URL::current());

        /** @phpstan-ignore-next-line  */
        app(TwillHttpBasicAuthRepository::class)->create([
            'domain' => '*',
            'published' => false,
        ]);

        if (filled($currentDomain)) {
            /** @phpstan-ignore-next-line  */
            app(TwillHttpBasicAuthRepository::class)->create([
                'domain' => $currentDomain,
                'published' => false,
            ]);
        }

        if (filled($appDomain) && $appDomain !== $currentDomain) {
            /** @phpstan-ignore-next-line  */
            app(TwillHttpBasicAuthRepository::class)->create([
                'domain' => $appDomain,
                'published' => false,
            ]);
        }
    }

    public function setIndexOptions(): void
    {
        $this->indexOptions = ['create' => !TwillHttpBasicAuthFacade::allDomainsEnabled()];
    }

    /**
     * @param array<string, mixed> $scopes
     * @param bool $forcePagination
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function getIndexItems($scopes = [], $forcePagination = false)
    {
        if (TwillHttpBasicAuthFacade::allDomainsEnabled()) {
            $scopes['domain'] = '*';
        }

        return parent::getIndexItems($scopes, $forcePagination);
    }

    /**
     * Saving a domain as published without a user name and a password is stopped with a red message (a validation error would only
     * show Twill's own text).
     */
    public function update(int|TwillModelContract $id, int|null $submoduleId = null): JsonResponse
    {
        $key = $id instanceof TwillModelContract ? $id->getKey() : $id;

        if (PublishingRequirements::missing($this->request->all(), $this->rowKey($key))) {
            return $this->respondWithError(PublishingRequirements::message());
        }

        return parent::update($id, $submoduleId);
    }

    private function rowKey(mixed $key): int|string|null
    {
        return is_int($key) || is_string($key) ? $key : null;
    }

    public function store($parentModuleId = null)
    {
        if (PublishingRequirements::missing($this->request->all(), null)) {
            return $this->respondWithError(PublishingRequirements::message());
        }

        return parent::store($parentModuleId);
    }

    public function publish(): JsonResponse
    {
        $all = $this->request->all();

        // `active` is the state now: a row that is not active is being turned on
        $id = $this->request->input('id');

        if (
            !filter_var($all['active'] ?? false, FILTER_VALIDATE_BOOLEAN) &&
            PublishingRequirements::missing(['published' => true], $this->rowKey($id))
        ) {
            return $this->respondWithError(PublishingRequirements::message());
        }

        $all['active'] = $all['active'] ?? false;

        $this->request->merge($all);

        return parent::publish();
    }

    protected function additionalIndexTableColumns(): TableColumns
    {
        $table = parent::additionalIndexTableColumns();

        $table->push(Text::make()->field('status')->title('Status'));

        $table->push(Text::make()->field('from_dot_env')->title('From .env'));

        $table->push(Text::make()->field('credentials_string')->title('Credentials'));

        $table->push(Text::make()->field('username')->title('Username'));

        $table->push(Text::make()->field('allow_laravel_login_string')->title('Laravel login'));

        $table->push(Text::make()->field('allow_twill_login_string')->title('Twill login'));

        return $table;
    }
}
