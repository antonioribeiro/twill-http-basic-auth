<?php

namespace A17\TwillHttpBasicAuth\Repositories;

use A17\Twill\Repositories\ModuleRepository;
use A17\Twill\Models\Contracts\TwillModelContract;
use A17\TwillHttpBasicAuth\Models\TwillHttpBasicAuth;
use A17\TwillHttpBasicAuth\Support\Facades\TwillHttpBasicAuth as TwillHttpBasicAuthFacade;

class TwillHttpBasicAuthRepository extends ModuleRepository
{
    public function __construct(TwillHttpBasicAuth $model)
    {
        $this->model = $model;
    }

    /**
     * The form shows the stored password, so the person sees that it is there and can change it. The model never gives it back on its
     * own (JSON, listings, revisions): it is read here, for this form only. The password of the .env is never shown.
     *
     * @return array<mixed>
     */
    public function getFormFields(TwillModelContract $object): array
    {
        $fields = parent::getFormFields($object);

        if ($object instanceof TwillHttpBasicAuth && !TwillHttpBasicAuthFacade::hasDotEnv()) {
            $fields['password'] = $object->storedPassword();
        }

        return $fields;
    }

    /**
     * An empty password field means "keep the stored one", not "remove it".
     *
     * @param array<mixed> $fields
     * @return array<mixed>
     */
    public function prepareFieldsBeforeSave(TwillModelContract $object, array $fields): array
    {
        if (blank($fields['password'] ?? null)) {
            unset($fields['password']);
        }

        return parent::prepareFieldsBeforeSave($object, $fields);
    }
}
