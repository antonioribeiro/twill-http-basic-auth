<?php

namespace A17\TwillHttpBasicAuth\Support;

/**
 * The credentials that protect one request, and the guards (logins of the application) that may also let it through.
 */
class Credentials
{
    /**
     * @param list<array{guard: string, column: string, conditions: array<string, mixed>}> $guards
     */
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly array $guards = [],
    ) {}
}
