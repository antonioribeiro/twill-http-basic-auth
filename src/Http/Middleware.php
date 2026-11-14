<?php

namespace A17\TwillHttpBasicAuth\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use A17\TwillHttpBasicAuth\Support\Throttle;
use Symfony\Component\HttpFoundation\Response;
use A17\TwillHttpBasicAuth\Support\Authenticator;
use A17\TwillHttpBasicAuth\Support\CredentialResolver;
use A17\TwillHttpBasicAuth\Exceptions\MisconfiguredProtection;

class Middleware
{
    public function __construct(
        private readonly CredentialResolver $resolver,
        private readonly Authenticator $authenticator,
        private readonly Throttle $throttle,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->ignored($request)) {
            return $next($request);
        }

        try {
            $credentials = $this->resolver->for($request);
        } catch (MisconfiguredProtection $e) {
            // closed, and said in the log: the visitor learns nothing about the credentials
            Log::error('HTTP Basic Auth is misconfigured for ' . $request->getHost() . ': ' . $e->getMessage());

            return $this->answer($request, 503, 'This site is protected and is not available right now.');
        }

        if ($credentials === null) {
            return $next($request);
        }

        if ($this->throttle->tooManyFailures($request, $credentials->scope)) {
            return $this->answer($request, 429, 'Too many attempts. Please wait and try again.', [
                'Retry-After' => (string) $this->throttle->retryAfter($request, $credentials->scope),
            ]);
        }

        if (!$this->authenticator->passes($request, $credentials)) {
            // a request with no credentials is the browser's first one (or a monitor, or a bot): it is asked, not counted as a guess
            if ($request->getUser() !== null || $request->getPassword() !== null) {
                $lockedOut = $this->throttle->recordFailure($request, $credentials->scope);

                // host and address only, never the user name or the password that was tried
                Log::warning('HTTP Basic Auth failed for ' . $request->getHost() . ' from ' . $request->ip() . '.');

                if ($lockedOut) {
                    Log::warning('HTTP Basic Auth locked out ' . $request->ip() . ' on ' . $request->getHost() . '.');
                }
            }

            return $this->answer($request, 401, 'Authentication required.', [
                'WWW-Authenticate' => 'Basic realm="' . $this->realm() . '"',
            ]);
        }

        $this->throttle->clear($request, $credentials->scope);

        $response = $next($request);

        // what is behind the password must never be kept by a shared cache
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @param array<string, string> $headers
     */
    private function answer(Request $request, int $status, string $message, array $headers = []): Response
    {
        $headers['Cache-Control'] = 'no-store, private';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status, $headers);
        }

        return response($message, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function realm(): string
    {
        $realm = config('twill-http-basic-auth.realm');

        return str_replace('"', '', is_string($realm) && $realm !== '' ? $realm : 'Restricted Area');
    }

    /**
     * Paths named in `ignore.paths` are left open. None by default: a path that is open on a protected host is a hole.
     */
    private function ignored(Request $request): bool
    {
        $paths = config('twill-http-basic-auth.ignore.paths');

        if (!is_array($paths) || $paths === []) {
            return false;
        }

        return $request->is(array_values(array_filter($paths, 'is_string')));
    }
}
