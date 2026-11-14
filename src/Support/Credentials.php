<?php

namespace A17\TwillHttpBasicAuth\Support;

/**
 * The credentials that protect one request, the scope they matched (`*` for the .env or a star row, else the host of the row: the
 * rate limit counts per scope, never per what the client sent as Host), and the guards (logins of the application) that may also let it through.
 */
class Credentials
{
    /**
     * @param list<array{guard: string, column: string, conditions: array<string, mixed>}> $guards
     */
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly string $scope,
        public readonly array $guards = [],
    ) {}
}
