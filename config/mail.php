<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        /*
         * Xolution's own relay (SPEC §7): smtp.xolution.nu, with
         * antispamcloud cleaning on the way out. The .nu is not a typo - the
         * mail addresses live on .nl and the SMTP host does not.
         *
         * The credentials arrived under XOL_ names, so they are read under
         * those names rather than renamed on the way in, and the MAIL_ keys
         * stay as the fallback so a machine configured the ordinary Laravel
         * way still works.
         */
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('XOL_SMTP', env('MAIL_HOST', '127.0.0.1')),

            // Deliberately not falling through to MAIL_PORT. That one carries
            // whatever a local mail catcher listens on, and pairing 2525 with
            // Xolution's relay would fail in a way that looks like a
            // credentials problem. The XOL_ keys are one unit: naming the host
            // means naming its port.
            //
            // 587 is submission with STARTTLS, which is what a relay like this
            // normally listens on; 465 and 2525 are the usual alternatives.
            // A guess rather than a reading - the host answers on none of them
            // from outside Xolution's own network, so which one it is has to
            // be told.
            'port' => env('XOL_SMTP') ? env('XOL_PORT', 587) : env('MAIL_PORT', 2525),

            // Relays of this kind usually authenticate as the address they
            // send from, so that is the default. XOL_USER is here for the
            // half of the time it is a separate account instead.
            'username' => env('XOL_USER', env('XOL_FROM', env('MAIL_USERNAME'))),
            'password' => env('XOL_PASS', env('MAIL_PASSWORD')),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('XOL_FROM', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

        // Only ever a fallback in practice: App\Mail\QuoteSent puts the
        // company name from the settings screen beside the address instead, so
        // a customer's inbox shows Xolution rather than the tool.
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
