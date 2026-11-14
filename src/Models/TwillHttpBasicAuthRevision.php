<?php

namespace A17\TwillHttpBasicAuth\Models;

use A17\Twill\Models\Revision;

/**
 * @method \Illuminate\Database\Eloquent\Relations\BelongsTo<\Illuminate\Database\Eloquent\Model, $this> user()
 */
class TwillHttpBasicAuthRevision extends Revision
{
    protected $table = 'twill_basic_auth_revisions';
}
