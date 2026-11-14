<?php

namespace A17\TwillHttpBasicAuth\Repositories;

use A17\Twill\Repositories\ModuleRepository;
use A17\Twill\Models\Contracts\TwillModelContract;
use A17\TwillHttpBasicAuth\Models\TwillHttpBasicAuth;

class TwillHttpBasicAuthRepository extends ModuleRepository
{
    public function __construct(TwillHttpBasicAuth $model)
    {
        $this->model = $model;
    }

    /**
     * The password field of the form is always empty (it is never sent back), so an empty field means "keep the stored one", not
     * "remove it".
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
