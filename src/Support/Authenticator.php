<?php

namespace A17\TwillHttpBasicAuth\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Does this request carry the right credentials? The comparison hashes both sides first and compares the digests with
 * `hash_equals`: its time does not depend on how much of a guess is right, nor on its length. Both fields are always compared.
 */
class Authenticator
{
    public function passes(Request $request, Credentials $credentials): bool
    {
        $username = (string) $request->getUser();
        $password = (string) $request->getPassword();

        $sameUsername = $this->same($credentials->username, $username);
        $samePassword = $this->same($credentials->password, $password);

        if ($sameUsername && $samePassword) {
            return true;
        }

        return $this->loggedInWithAGuard($credentials, $username, $password);
    }

    private function same(string $expected, string $given): bool
    {
        return hash_equals(hash('sha256', $expected), hash('sha256', $given));
    }

    /**
     * The Basic credentials are an account of the application (`validate()` checks them without starting a session). A session is
     * not looked at: the password is asked before the session starts, so it would never be there.
     */
    private function loggedInWithAGuard(Credentials $credentials, string $username, string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }

        foreach ($credentials->guards as $definition) {
            $guard = Auth::guard($definition['guard']);

            if (
                $guard->validate(
                    [$definition['column'] => $username, 'password' => $password] + $definition['conditions'],
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
