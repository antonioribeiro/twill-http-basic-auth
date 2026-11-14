<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use A17\TwillHttpBasicAuth\Tests\TestCase;
use A17\TwillHttpBasicAuth\Http\Controllers\TwillHttpBasicAuthController;

/**
 * The module holds the shared password, so only who manages users (ADMIN and superadmin) reaches it: not a PUBLISHER, not a
 * VIEWONLY. Twill's own module gates (`list`, `edit`, `publish`, `delete`) would let editors in.
 */
class ModuleAccessTest extends TestCase
{
    public function test_every_action_of_the_module_needs_the_manage_users_gate(): void
    {
        $controller = app(TwillHttpBasicAuthController::class);

        $gates = [];

        foreach ($controller->getMiddleware() as $middleware) {
            $name = is_array($middleware) ? $middleware['middleware'] ?? null : null;

            if (is_string($name) && str_starts_with($name, 'can:')) {
                $gates[$name] = $name;
            }
        }

        $this->assertSame(['can:manage-users'], array_values($gates));
    }
}
