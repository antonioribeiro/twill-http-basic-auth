<?php

namespace A17\TwillHttpBasicAuth\Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;

/**
 * What Twill's `Authenticate` does to a visitor who is not logged in: redirect to the login page. Laravel sorts every middleware
 * that implements `AuthenticatesRequests` ahead of the others, so without care it would run before the Basic Auth question.
 */
class RedirectingAuthenticate implements AuthenticatesRequests
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return redirect('/login');
    }
}
