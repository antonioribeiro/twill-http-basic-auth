<?php

namespace A17\TwillHttpBasicAuth\Support;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

/**
 * Slows down guessing. Only failed attempts count, one counter per visitor (IP address) and scope (what matched: the host of a row, or `*`, never the Host header the client sent): a correct login never counts
 * and clears the counter, and nobody can lock out another visitor. Past the limit the answer is 429 for the rest of the minute.
 */
class Throttle
{
    private const DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function tooManyFailures(Request $request, string $scope): bool
    {
        return $this->limiter->tooManyAttempts($this->key($request, $scope), $this->limit());
    }

    /**
     * Counts a failure. True when this failure is the one that reaches the limit: the moment the visitor is locked out.
     */
    public function recordFailure(Request $request, string $scope): bool
    {
        return $this->limiter->hit($this->key($request, $scope), self::DECAY_SECONDS) === $this->limit();
    }

    public function clear(Request $request, string $scope): void
    {
        $key = $this->key($request, $scope);

        // a request with the right credentials is the common one: no cache write when there is nothing to clear
        if ($this->limiter->attempts($key) > 0) {
            $this->limiter->clear($key);
        }
    }

    public function retryAfter(Request $request, string $scope): int
    {
        return max(1, $this->limiter->availableIn($this->key($request, $scope)));
    }

    private function key(Request $request, string $scope): string
    {
        return 'basic-auth:' . sha1($scope . '|' . $request->ip());
    }

    private function limit(): int
    {
        $limit = config('twill-http-basic-auth.rate-limiting.attempts-per-minute');

        return is_numeric($limit) ? max(1, (int) $limit) : 10;
    }
}
