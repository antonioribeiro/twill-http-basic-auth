<?php

return [
    'enabled' => env('TWILL_HTTP_BASIC_AUTH_ENABLED', false),

    // The name the browser shows in its password prompt.
    'realm' => env('TWILL_HTTP_BASIC_AUTH_REALM', 'Restricted Area'),

    // Failed attempts a visitor (one IP address on one host) may make per minute before the answer is 429.
    'rate-limiting' => [
        'attempts-per-minute' => (int) env('TWILL_HTTP_BASIC_AUTH_RATE_LIMITING_ATTEMPTS', 10),
    ],

    // Paths left open on a protected host. None by default: an open path is a hole. Example: ['up', 'api/health'].
    'ignore' => [
        'paths' => [],
    ],

    'keys' => [
        'username' => env('TWILL_HTTP_BASIC_AUTH_USERNAME'),
        'password' => env('TWILL_HTTP_BASIC_AUTH_PASSWORD'),
    ],

    'inputs' => [
        'username' => ['type' => 'text'],
        'password' => ['type' => 'password'],
    ],

    'middleware' => [
        // A global middleware: every request of a protected host, whatever its route. Turn it off to add the middleware yourself
        // (`\A17\TwillHttpBasicAuth\Http\Middleware`) where you want it.
        'automatic' => true,

        'class' => \A17\TwillHttpBasicAuth\Http\Middleware::class,
    ],

    'database-login' => [
        'twill' => [
            'enabled' => env('TWILL_HTTP_BASIC_AUTH_TWILL_DATABASE_LOGIN_ENABLED', false),

            'username-column' => 'email',

            'guard' => 'twill_users',

            // what the account must also be (Twill's own login asks for `published`: a disabled account does not pass here either)
            'conditions' => ['published' => 1],
        ],

        'laravel' => [
            'enabled' => env('TWILL_HTTP_BASIC_AUTH_LARAVEL_DATABASE_LOGIN_ENABLED', false),

            'username-column' => 'email',

            'guard' => 'web',

            'conditions' => [],
        ],
    ],
];
