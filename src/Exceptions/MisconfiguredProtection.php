<?php

namespace A17\TwillHttpBasicAuth\Exceptions;

use RuntimeException;

/**
 * A host is protected but its credentials cannot be used (missing, empty, undecryptable, half set). The middleware answers 503:
 * a mistake in the admin or in the .env must never open a site.
 */
class MisconfiguredProtection extends RuntimeException {}
