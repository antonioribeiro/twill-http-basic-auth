<?php

namespace A17\TwillHttpBasicAuth\Support;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

/**
 * Slows down guessing. Only failed attempts count, one counter per visitor (IP address) and host: a correct login never counts
 * and clears the counter, and nobody can lock out another visitor. Past the limit the answer is 429 for the rest of the minute.
 */
class Throttle
{
    private const DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function tooManyFailures(Request $request): bool
    {
        return $this->limiter->tooManyAttempts($this->key($request), $this->limit());
    }

    public function recordFailure(Request $request): void
    {
        $this->limiter->hit($this->key($request), self::DECAY_SECONDS);
    }

    public function clear(Request $request): void
    {
        $key = $this->key($request);

        // a request with the right credentials is the common one: no cache write when there is nothing to clear
        if ($this->limiter->attempts($key) > 0) {
            $this->limiter->clear($key);
        }
    }

    public function retryAfter(Request $request): int
    {
        return max(1, $this->limiter->availableIn($this->key($request)));
    }

    private function key(Request $request): string
    {
        return 'basic-auth:' . sha1(CredentialResolver::normalizeHost($request->getHost()) . '|' . $request->ip());
    }

    private function limit(): int
    {
        $limit = config('twill-http-basic-auth.rate-limiting.attempts-per-minute');

        return is_numeric($limit) ? max(1, (int) $limit) : 10;
    }
}
