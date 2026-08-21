<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
     * The existing Chromium container that turns the quote template into a PDF
     * (SPEC §1). Basic auth, because that is what the container in front of it
     * expects. Absent config is a supported state: the download refuses with an
     * explanation rather than failing at the request.
     *
     * The credentials are read under the names Gotenberg itself uses, so the
     * same pair can be pasted into the container's environment on Render and
     * into this application's without being translated on the way.
     * GOTENBERG_USERNAME and GOTENBERG_PASSWORD stay as a fallback for
     * anything already configured that way.
     *
     * The container's own toggle is API_ENABLE_BASIC_AUTH, without the
     * GOTENBERG_ prefix its credentials carry. That asymmetry is Gotenberg's
     * rather than a typo, and it belongs on the container: this application
     * sends credentials whenever it has them and needs no switch.
     */
    'gotenberg' => [
        'url' => env('GOTENBERG_URL'),
        'username' => env('GOTENBERG_API_BASIC_AUTH_USERNAME', env('GOTENBERG_USERNAME')),
        'password' => env('GOTENBERG_API_BASIC_AUTH_PASSWORD', env('GOTENBERG_PASSWORD')),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 30),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
